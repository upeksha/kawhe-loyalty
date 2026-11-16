<?php

use App\Mail\ConfirmCustomerReplyTo;
use App\Mail\CustomerCardRecovery;
use App\Mail\CustomerWelcomeEmail;
use App\Mail\VerifyCustomerEmail;
use App\Models\LoyaltyAccount;
use App\Models\Store;
use App\Notifications\VerifyLoyaltyAccount;
use Illuminate\Support\Facades\Mail;

test('only the store owner can change and preview the customer email settings', function () {
    $store = Store::factory()->create();
    $other = Store::factory()->create()->user;
    $url = route('merchant.stores.customer-email.update', $store);
    $preview = route('merchant.stores.customer-email.preview', $store);

    $this->actingAs($other)->put($url, ['welcome_note' => 'Other café'])->assertForbidden();
    $this->get($preview)->assertForbidden();
    $this->actingAs($store->user)->put($url, ['welcome_note' => 'See you at the counter!'])->assertRedirect();
    $this->get($preview)->assertOk()->assertSee('See you at the counter!')->assertSee('Preview of your saved welcome email');
});

test('reply addresses must be confirmed and a superseded link cannot change the address', function () {
    Mail::fake();
    $store = Store::factory()->create();
    $store->forceFill(['customer_reply_to' => 'old@example.com'])->save();
    $settings = route('merchant.stores.customer-email.update', $store);
    $this->actingAs($store->user)->put($settings, ['reply_to' => 'first@example.com'])->assertRedirect();
    $first = Mail::queued(ConfirmCustomerReplyTo::class)->sole()->confirmationUrl;
    $this->put($settings, ['reply_to' => 'second@example.com'])->assertRedirect();
    $second = Mail::queued(ConfirmCustomerReplyTo::class)->last()->confirmationUrl;

    $this->get($first)->assertGone();
    $this->get($second)->assertOk();
    expect($store->fresh()->customer_reply_to)->toBe('old@example.com');

    $this->post($second)->assertRedirect(route('store.customer-email.confirmed'));
    expect($store->fresh()->customer_reply_to)->toBe('second@example.com');
    $this->post($second)->assertGone();
    $this->put($settings, ['reply_to' => ''])->assertRedirect();
    expect($store->fresh()->customer_reply_to)->toBeNull();
});

test('all customer mail paths use the issuing cafe brand and only its confirmed reply address', function () {
    config(['mail.from.address' => 'loyalty@kawhe.shop', 'app.url' => 'https://testing.kawhe.shop']);
    $cafe = Store::factory()->create(['name' => 'The Corner Café', 'logo_path' => 'logos/corner.png']);
    $cafe->forceFill([
        'customer_welcome_note' => 'Thanks for coming in! <b>See you soon</b>',
        'customer_reply_to' => 'team@example.com',
        'pending_customer_reply_to' => 'unverified@example.com',
    ])->save();
    $account = LoyaltyAccount::factory()->create(['store_id' => $cafe->id]);
    $welcome = new CustomerWelcomeEmail($account->customer, $account, 'welcome-token');
    $verification = new VerifyCustomerEmail('verify-token', $account->public_token, $cafe);
    $recovery = new CustomerCardRecovery('https://testing.kawhe.shop/recover/test', $cafe->name, false, $cafe);

    foreach ([$welcome, $verification, $recovery] as $mail) {
        $envelope = $mail->envelope();
        expect($envelope->from->address)->toBe('loyalty@kawhe.shop')
            ->and($envelope->from->name)->toBe('The Corner Café')
            ->and($envelope->replyTo[0]->address)->toBe('team@example.com')
            ->and($mail->render())->toContain('The Corner Café', 'logos/corner.png', 'Powered by Kawhe')
            ->not->toContain('unverified@example.com');
    }

    $notification = (new VerifyLoyaltyAccount($account))->toMail($account);
    expect($notification->from)->toBe(['loyalty@kawhe.shop', 'The Corner Café'])
        ->and($notification->replyTo[0][0])->toBe('team@example.com')
        ->and((string) $notification->render())->toContain('logos/corner.png', 'one hour');
    expect($welcome->render())->toContain('Thanks for coming in! &lt;b&gt;See you soon&lt;/b&gt;');
    expect($verification->render())->not->toContain('Thanks for coming in!');

    $other = Store::factory()->create(['name' => 'Another Café', 'logo_path' => null, 'pass_logo_path' => null]);
    $otherRecovery = new CustomerCardRecovery('https://testing.kawhe.shop/recover/other', $other->name, true, $other);
    expect($otherRecovery->envelope()->replyTo)->toBe([])
        ->and($otherRecovery->render())->toContain('Another Café')->not->toContain('<img', 'The Corner Café');
});

test('customer mail takes the store default card colours with a readable button', function () {
    $store = Store::factory()->create();
    $card = $store->resolvedDefaultProgram();
    $card->forceFill(['card_design' => 'simple', 'background_color' => '#DBB253'])->save();

    $preview = route('merchant.stores.customer-email.preview', $store);
    $this->actingAs($store->user)->get($preview)->assertOk()
        ->assertSee('bgcolor="#DBB253"', false)
        ->assertSee('padding:14px 24px;color:#000000', false);

    $card->forceFill(['background_color' => '#25285A'])->save();
    $recovery = new CustomerCardRecovery('https://testing.kawhe.shop/recover/test', $store->name, false, $store->fresh());

    expect($recovery->render())
        ->toContain('bgcolor="#25285A"', 'padding:14px 24px;color:#FFFFFF')
        ->not->toContain('bgcolor="#DBB253"');
});

test('customer mail prefers the default card customer logo and falls back to the store logo', function () {
    $store = Store::factory()->create(['logo_path' => 'logos/original-store.png']);
    $card = $store->resolvedDefaultProgram();
    $card->forceFill([
        'logo_path' => 'logos/current-card.png',
        'pass_logo_path' => 'pass-logos/wallet.png',
    ])->save();

    $preview = route('merchant.stores.customer-email.preview', $store);
    $this->actingAs($store->user)->get($preview)->assertOk()
        ->assertSee('logos/current-card.png')
        ->assertDontSee('logos/original-store.png')
        ->assertDontSee('pass-logos/wallet.png');

    $recovery = new CustomerCardRecovery('https://testing.kawhe.shop/recover/test', $store->name, false, $store->fresh());
    expect($recovery->render())->toContain('logos/current-card.png')->not->toContain('logos/original-store.png');

    $card->forceFill(['logo_path' => null])->save();
    $this->get($preview)->assertOk()->assertSee('logos/original-store.png');
});
