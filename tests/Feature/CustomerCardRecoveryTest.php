<?php

use App\Mail\CustomerCardRecovery;
use App\Models\Customer;
use App\Models\LoyaltyAccount;
use App\Models\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    $this->store = Store::factory()->create();
    $this->program = $this->store->resolvedDefaultProgram();
    $this->customer = Customer::factory()->create(['email' => 'owner@example.com', 'phone' => '0412345678']);
    $this->account = LoyaltyAccount::factory()->create([
        'customer_id' => $this->customer->id,
        'store_id' => $this->store->id,
        'loyalty_program_id' => $this->program->id,
    ]);
    $this->lookupUrl = route('join.lookup', ['slug' => $this->program->slug, 't' => $this->program->join_token]);
    $this->joinUrl = route('join.store', ['slug' => $this->program->slug, 't' => $this->program->join_token]);
});

function recoveryUrl(): string
{
    return Mail::queued(CustomerCardRecovery::class)->last()->recoveryUrl;
}

test('lookup gives the same neutral response for known and unknown emails without exposing the card', function () {
    $known = $this->post($this->lookupUrl, ['email' => $this->customer->email]);
    $known->assertOk()->assertViewIs('join.recovery-sent')->assertDontSee($this->account->public_token);
    $unknown = $this->post($this->lookupUrl, ['email' => 'unknown@example.com']);
    $unknown->assertOk()->assertViewIs('join.recovery-sent');
    expect($known->getContent())->toBe($unknown->getContent());
    Mail::assertQueued(CustomerCardRecovery::class, fn ($mail) => $mail->hasTo('owner@example.com'));
    Mail::assertQueuedCount(1);
    $rawToken = basename(recoveryUrl());
    $this->assertDatabaseHas('customer_card_recoveries', ['token_hash' => hash('sha256', $rawToken)]);
    $this->assertDatabaseMissing('customer_card_recoveries', ['token_hash' => $rawToken]);
    expect(Mail::queued(CustomerCardRecovery::class)->first()->render())->not->toContain($this->account->public_token);
});

test('email scanners do not consume links and explicit confirmation opens a card only once', function () {
    $this->post($this->lookupUrl, ['email' => $this->customer->email]);
    $url = recoveryUrl();
    $this->get($url)->assertOk()->assertViewIs('join.recovery-confirm')->assertDontSee($this->account->public_token)
        ->assertHeader('Referrer-Policy', 'no-referrer');
    $this->get($url)->assertOk();
    $this->post($url)->assertRedirect(route('card.show', ['public_token' => $this->account->public_token]));
    $this->post($url)->assertStatus(410)->assertDontSee($this->account->public_token);
    $this->get($url)->assertStatus(410);
    expect($this->account->fresh()->verified_at)->not->toBeNull();
});

test('expired and forged recovery links cannot expose cards', function () {
    $this->post($this->lookupUrl, ['email' => $this->customer->email]);
    $url = recoveryUrl();
    $this->post(route('customer.card.recover', ['token' => str_repeat('z', 64)]))->assertStatus(410);
    $this->travel(21)->minutes();
    $this->post($url)->assertStatus(410);
});

test('changing the customer email invalidates outstanding recovery links', function () {
    $this->post($this->lookupUrl, ['email' => $this->customer->email]);
    $this->customer->update(['email' => 'changed@example.com']);
    $this->post(recoveryUrl())->assertStatus(410);
});

test('archived programs and stores cannot be recovered', function () {
    $this->post($this->lookupUrl, ['email' => $this->customer->email]);
    $this->program->delete();
    $this->post(recoveryUrl())->assertStatus(410);
    $this->program->restore();
    $this->store->delete();
    $this->post(recoveryUrl())->assertStatus(410);
});

test('repeat signup cannot overwrite the customer or reveal an existing card', function () {
    $original = $this->customer->fresh()->getAttributes();
    $this->post($this->joinUrl, ['email' => $this->customer->email, 'name' => 'Attacker'])
        ->assertOk()->assertViewIs('join.recovery-sent')->assertDontSee($this->account->public_token);
    expect($this->customer->fresh()->getAttributes())->toBe($original);
    expect(LoyaltyAccount::count())->toBe(1);
    $this->post(recoveryUrl())->assertRedirect(route('card.show', ['public_token' => $this->account->public_token]));
});

test('phone matching never replaces the registered email or sends a link to an attacker', function () {
    $this->program->update(['registration_form_config' => ['phone' => ['enabled' => true, 'required' => false]]]);
    $this->post($this->joinUrl, ['email' => 'attacker@example.com', 'phone' => $this->customer->phone, 'name' => 'Attacker'])
        ->assertOk()->assertViewIs('join.recovery-sent');
    expect($this->customer->fresh()->email)->toBe('owner@example.com');
    Mail::assertQueued(CustomerCardRecovery::class, fn ($mail) => $mail->hasTo('owner@example.com') && ! $mail->hasTo('attacker@example.com'));
    $this->post($this->lookupUrl, ['phone' => $this->customer->phone])->assertOk()->assertViewIs('join.recovery-sent');
    expect(LoyaltyAccount::count())->toBe(1);
});

test('phone only customers get no public card or recovery email', function () {
    $this->program->update(['registration_form_config' => ['phone' => ['enabled' => true, 'required' => false]]]);
    $this->customer->update(['email' => null]);
    $this->post($this->lookupUrl, ['phone' => $this->customer->phone])->assertOk()->assertViewIs('join.recovery-sent');
    Mail::assertNothingQueued();
});

test('existing identity enrollment waits for email ownership and cannot access another program', function () {
    $other = $this->store->loyaltyPrograms()->create(['name' => 'Other programme', 'reward_target' => 3, 'reward_title' => 'Coffee']);
    $lookup = route('join.lookup', ['slug' => $other->slug, 't' => $other->join_token]);
    $this->post($lookup, ['email' => $this->customer->email])->assertOk();
    Mail::assertNothingQueued();
    $this->post(route('join.store', ['slug' => $other->slug, 't' => $other->join_token]), ['email' => $this->customer->email])
        ->assertOk()->assertViewIs('join.recovery-sent');
    expect(LoyaltyAccount::count())->toBe(1);
    $this->post(recoveryUrl())->assertRedirect();
    $new = LoyaltyAccount::where('loyalty_program_id', $other->id)->firstOrFail();
    expect($new->customer_id)->toBe($this->customer->id);
    expect($new->public_token)->not->toBe($this->account->public_token);
    expect(LoyaltyAccount::count())->toBe(2);
});

test('recovery does not recreate a deleted account without enrollment permission', function () {
    $this->post($this->lookupUrl, ['email' => $this->customer->email]);
    $this->account->delete();
    $this->post(recoveryUrl())->assertStatus(410);
    expect(LoyaltyAccount::count())->toBe(0);
});

test('recipient cooldown applies across signup and lookup without invalidating earlier mail', function () {
    $this->post($this->lookupUrl, ['email' => $this->customer->email]);
    $first = recoveryUrl();
    $this->post($this->joinUrl, ['email' => $this->customer->email]);
    $second = recoveryUrl();
    $this->post($this->lookupUrl, ['email' => $this->customer->email])->assertOk();
    Mail::assertQueuedCount(2);
    $this->post($first)->assertRedirect();
    $this->post($second)->assertStatus(410);
});

test('lookup endpoint enforces per IP request limits', function () {
    foreach (range(1, 10) as $i) {
        $this->post($this->lookupUrl, ['email' => 'unknown@example.com'])->assertOk();
    }
    $this->post($this->lookupUrl, ['email' => 'unknown@example.com'])->assertStatus(429);
});

test('repeat signup endpoint also enforces per IP request limits', function () {
    foreach (range(1, 10) as $i) {
        $this->post($this->joinUrl, ['email' => $this->customer->email])->assertOk();
    }
    $this->post($this->joinUrl, ['email' => $this->customer->email])->assertStatus(429);
});

test('new enrollment checks the plan limit again when the email link is consumed', function () {
    $other = $this->store->loyaltyPrograms()->create(['name' => 'Other', 'reward_target' => 3, 'reward_title' => 'Coffee']);
    LoyaltyAccount::factory()->create(['store_id' => $this->store->id, 'loyalty_program_id' => $other->id, 'customer_id' => $this->customer->id]);
    $this->account->delete();
    $this->post($this->joinUrl, ['email' => $this->customer->email])->assertOk();
    $this->mock(\App\Services\Billing\UsageService::class, function ($mock) {
        $mock->shouldReceive('canAcceptNewCustomer')->once()->andReturn(false);
    });
    $this->post(recoveryUrl())->assertOk()->assertViewIs('join.limit-reached');
    expect(LoyaltyAccount::count())->toBe(1);
});

test('queue failure leaves no usable token and keeps the neutral response', function () {
    Mail::shouldReceive('to')->once()->andReturnSelf();
    Mail::shouldReceive('queue')->once()->andThrow(new \RuntimeException('Queue unavailable'));
    $this->post($this->lookupUrl, ['email' => $this->customer->email])
        ->assertOk()->assertViewIs('join.recovery-sent');
    expect(DB::table('customer_card_recoveries')->count())->toBe(0);
});

test('email casing does not bypass existing customer ownership checks', function () {
    $this->post($this->joinUrl, ['email' => 'OWNER@example.com'])->assertOk()->assertViewIs('join.recovery-sent');
    $this->post($this->lookupUrl, ['email' => 'OWNER@example.com'])->assertOk()->assertViewIs('join.recovery-sent');
    expect(Customer::count())->toBe(1);
    expect(LoyaltyAccount::count())->toBe(1);
    Mail::assertQueuedCount(2);
});


test('customer card and signup offer program scoped recovery and privacy safe help links', function () {
    $url = route('join.existing', ['slug' => $this->program->slug, 't' => $this->program->join_token]);
    foreach ([route('card.show', ['public_token' => $this->account->public_token]), route('join.show', ['slug' => $this->program->slug, 't' => $this->program->join_token])] as $page) {
        $this->get($page)->assertOk()->assertSee('Recover my card')->assertSee($url)
            ->assertSee('https://kawhe.shop/privacy-policy')->assertSee('mailto:support@kawhe.shop')
            ->assertSee('<meta name="referrer" content="no-referrer">', false);
    }
    $this->get($url)->assertOk()->assertDontSee($this->account->public_token);
});

test('recovering a styled card preserves partial stamps rewards and its existing identity', function (string $style) {
    config(['app.env' => 'testing', 'app.url' => 'https://testing.kawhe.shop']);
    \Illuminate\Support\Facades\Storage::fake('public');
    $this->program->update(['card_design' => $style, 'reward_target' => 6, 'card_pattern' => 'botanical', 'stamp_icon' => 'bean']);
    $this->account->update(['stamp_count' => 2, 'reward_balance' => 1, 'redeem_token' => 'RECOVERY-REWARD']);
    $identity = $this->account->only(['id', 'public_token', 'manual_entry_code', 'redeem_token', 'stamp_count', 'reward_balance']);
    $this->post($this->lookupUrl, ['email' => $this->customer->email])->assertOk();
    $url = recoveryUrl();
    $this->get($url)->assertOk();
    $this->get($url)->assertOk();
    $this->post($url)->assertRedirect(route('card.show', ['public_token' => $this->account->public_token]));
    expect($this->account->fresh()->only(array_keys($identity)))->toBe($identity);
    expect(LoyaltyAccount::where('customer_id', $this->customer->id)->count())->toBe(1);
    $this->getJson('/api/card/'.$this->account->public_token)->assertOk();
    $this->post($url)->assertStatus(410);
    expect($this->account->fresh()->only(array_keys($identity)))->toBe($identity);
})->with(['simple', 'illustrated', 'photo']);


test('fresh signup and repeat signup behave consistently across deployment environments', function (string $environment) {
    config(['app.env' => $environment, 'app.url' => $environment === 'production' ? 'https://app.kawhe.shop' : 'https://testing.kawhe.shop']);
    $this->post($this->joinUrl, ['email' => 'new-customer@example.invalid', 'name' => 'TEST New Customer'])->assertRedirect();
    $customer = Customer::where('email', 'new-customer@example.invalid')->sole();
    $card = $customer->loyaltyAccounts()->sole();
    $this->post($this->joinUrl, ['email' => $customer->email])->assertOk()->assertViewIs('join.recovery-sent')->assertDontSee($card->public_token);
    expect(parse_url(recoveryUrl(), PHP_URL_HOST))->toBe(parse_url(config('app.url'), PHP_URL_HOST));
    $this->get(recoveryUrl())->assertOk()->assertViewIs('join.recovery-confirm');
    $this->post(recoveryUrl())->assertRedirect(route('card.show', ['public_token' => $card->public_token]));
    expect($customer->loyaltyAccounts()->count())->toBe(1);
})->with(['testing', 'production']);

test('signup explains separate store enrollment without exposing or changing existing cards', function () {
    $this->store->update(['name' => 'New Cafe']);
    $other = $this->store->loyaltyPrograms()->create(['name' => 'Other programme', 'reward_target' => 3, 'reward_title' => 'Coffee']);
    $this->post(route('join.store', ['slug' => $other->slug, 't' => $other->join_token]), ['email' => $this->customer->email])
        ->assertOk()->assertSee('Check your email to continue')->assertSee('New Cafe')->assertSee('stay separate')->assertDontSee($this->account->public_token)
        ->assertSee(e(route('join.show', ['slug' => $other->slug, 't' => $other->join_token])), false);
    $mail = Mail::queued(CustomerCardRecovery::class)->last();
    expect($mail->envelope()->subject)->toBe('Continue to New Cafe');
    expect($mail->render())->toContain('Continue to New Cafe', 'separate loyalty card', '20 minutes');
    $this->get($mail->recoveryUrl)->assertOk()->assertSee('Confirm and continue');
    expect(LoyaltyAccount::count())->toBe(1);
    $this->post($mail->recoveryUrl)->assertRedirect();
    expect(LoyaltyAccount::count())->toBe(2);
    $this->post($mail->recoveryUrl)->assertStatus(410);
    expect($this->account->fresh()->public_token)->toBe($this->account->public_token);
});

test('explicit recovery retains recovery email wording', function () {
    $this->post($this->lookupUrl, ['email' => $this->customer->email])->assertOk()->assertSee('Check your email')->assertDontSee('Check your email to continue');
    $mail = Mail::queued(CustomerCardRecovery::class)->last();
    expect($mail->joining)->toBeFalse();
    expect($mail->envelope()->subject)->toBe('Open your loyalty card at '.$this->store->name);
    expect($mail->render())->toContain('Open my card')->not->toContain('separate loyalty card');
});
