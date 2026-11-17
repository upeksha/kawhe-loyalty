<?php

use App\Models\LoyaltyAccount;
use App\Models\StampEvent;
use App\Models\User;
use App\Services\MerchantAcquisition;
use Illuminate\Http\Client\Request as OutboundRequest;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    config(['ads_pilot.enabled' => true, 'ads_pilot.pixel_id' => 'test-pixel', 'ads_pilot.conversion_key' => 'test-key']);
    Http::preventStrayRequests();
    Mail::fake();
    Notification::fake();
    Queue::fake();
});

function createAdsPilotOwner(bool $consent): User
{
    $thisTest = test();
    $thisTest->get('/register?utm_source=chatgpt_ads&utm_medium=cpc&utm_campaign=kawhe_nz_cafe_pilot&utm_content=paper_cards_a')->assertOk();
    $thisTest->post('/register/started')->assertNoContent();
    $thisTest->post('/register/started')->assertNoContent();
    $thisTest->post('/register', [
        'name' => 'Example Owner', 'store_name' => 'Example Café',
        'email' => 'ads-pilot@example.invalid', 'password' => 'local-test-password',
        'password_confirmation' => 'local-test-password', 'ads_click_reference' => 'opaque+Test/ID=',
        'ads_measurement_consent' => $consent ? '1' : '0',
    ])->assertRedirect(route('verification.notice'));

    return User::where('email', 'ads-pilot@example.invalid')->sole();
}

test('registration tracks one start and one completed account without sharing unconsented click identifiers', function () {
    $user = createAdsPilotOwner(false);
    $events = app(MerchantAcquisition::class)->events();
    expect((clone $events)->where('event', 'signup_started')->count())->toBe(1);
    $row = (clone $events)->where('user_id', $user->id)->where('event', 'signup_completed')->sole();
    expect($row->creative)->toBe('paper_cards_a')->and($row->encrypted_click_reference)->toBeNull();
    expect($user->signup_attribution['utm_campaign'])->toBe(MerchantAcquisition::CAMPAIGN);
    Http::assertNothingSent();
});

test('only reviewed verified merchants with consent can send one stable server conversion', function () {
    $user = createAdsPilotOwner(true);
    $acquisition = app(MerchantAcquisition::class);
    $row = $acquisition->events()->where('event', 'signup_completed')->sole();
    expect($row->encrypted_click_reference)->not->toContain('opaque+Test/ID=')
        ->and(Crypt::decryptString($row->encrypted_click_reference))->toBe('opaque+Test/ID=');
    $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
        'id' => $user->id, 'hash' => sha1($user->getEmailForVerification()),
    ], absolute: false);
    $this->get($url)->assertOk();
    expect($acquisition->events()->where('event', 'email_verified')->count())->toBe(0);
    $this->post($url)->assertRedirect();
    $this->post($url)->assertRedirect();
    expect($acquisition->events()->where('event', 'email_verified')->count())->toBe(1)
        ->and($acquisition->sendVerifiedRegistration($user->id, true))->toBeFalse();
    Http::assertNothingSent();
    $acquisition->classify($user->fresh(), true);
    Http::fake(['https://bzr.openai.com/v1/events*' => Http::response([], 200)]);
    expect($acquisition->sendVerifiedRegistration($user->id, true))->toBeTrue()
        ->and($acquisition->sendVerifiedRegistration($user->id, false))->toBeTrue()
        ->and($acquisition->sendVerifiedRegistration($user->id, false))->toBeFalse();
    Http::assertSentCount(2);
    foreach ([true, false] as $validateOnly) {
        Http::assertSent(fn (OutboundRequest $req) => $req['validate_only'] === $validateOnly
            && $req['events'][0]['id'] === $row->event_id
            && $req['events'][0]['type'] === 'registration_completed'
            && $req['events'][0]['oppref'] === 'opaque+Test/ID='
            && !isset($req['events'][0]['user'])
            && $req['events'][0]['data'] === ['type' => 'customer_action']);
    }
});

test('activation requires a qualified owner and a real recorded stamp selected for review', function () {
    $owner = createAdsPilotOwner(false);
    $acquisition = app(MerchantAcquisition::class);
    $store = $owner->stores()->sole();
    $acquisition->record($owner, 'programme_published');
    $card = LoyaltyAccount::factory()->create(['store_id' => $store->id, 'loyalty_program_id' => $store->resolvedDefaultProgram()->id]);
    $stamp = StampEvent::create(['loyalty_account_id' => $card->id, 'store_id' => $store->id, 'user_id' => $owner->id, 'type' => 'stamp', 'count' => 1]);
    expect($acquisition->confirmActivation($owner, $stamp))->toBeFalse();
    $acquisition->classify($owner, true);
    expect($acquisition->confirmActivation($owner, $stamp))->toBeTrue()
        ->and($acquisition->confirmActivation($owner, $stamp))->toBeTrue()
        ->and($acquisition->events()->where('event', 'cafe_activated')->count())->toBe(1);
});

test('successful Pro invoice values are retained once per invoice and renewals do not add another café', function () {
    $owner = createAdsPilotOwner(false);
    $acquisition = app(MerchantAcquisition::class);
    config(['cashier.price_id' => 'price_pro']);
    $invoice = [
        'id' => 'in_pilot_1', 'status' => 'paid', 'currency' => 'nzd', 'amount_paid' => 950,
        'status_transitions' => ['paid_at' => now()->timestamp],
        'lines' => ['data' => [['pricing' => ['price_details' => ['price' => 'price_pro']]]]],
    ];
    $acquisition->recordInvoice($owner, $invoice);
    $acquisition->recordInvoice($owner, $invoice);
    $acquisition->recordInvoice($owner, array_replace($invoice, ['id' => 'in_renewed', 'amount_paid' => 1000]));
    $acquisition->recordInvoice($owner, array_replace($invoice, ['id' => 'in_zero', 'amount_paid' => 0]));
    $acquisition->recordInvoice($owner, array_replace($invoice, ['id' => 'in_manual', 'paid_out_of_band' => true]));
    $payments = $acquisition->events()->where('event', 'pro_payment');
    expect((clone $payments)->count())->toBe(2)
        ->and((clone $payments)->sum('amount_minor'))->toBe(1950)
        ->and((clone $payments)->distinct()->count('user_id'))->toBe(1);
});
