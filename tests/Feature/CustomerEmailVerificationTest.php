<?php

use App\Mail\VerifyCustomerEmail;
use App\Models\Customer;
use App\Models\LoyaltyAccount;
use App\Models\Store;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

test('send endpoint sends email and sets token in database', function () {
    Mail::fake();

    $store = Store::factory()->create();
    $customer = Customer::factory()->create(['email' => 'test@example.com']);
    $account = LoyaltyAccount::factory()->create([
        'store_id' => $store->id,
        'customer_id' => $customer->id,
    ]);

    $response = $this->post(route('customer.email.verification.send', ['public_token' => $account->public_token]));

    $response->assertRedirect();
    $response->assertSessionHas('message');

    Mail::assertQueued(VerifyCustomerEmail::class, function ($mail) use ($customer) {
        return $mail->hasTo($customer->email);
    });

    $customer->refresh();
    expect($customer->email_verification_token_hash)->not->toBeNull();
    expect($customer->email_verification_expires_at)->not->toBeNull();
    expect($customer->email_verification_sent_at)->not->toBeNull();
});

test('verify endpoint sets email_verified_at and clears token', function () {
    $store = Store::factory()->create();
    $customer = Customer::factory()->create(['email' => 'test@example.com']);
    $account = LoyaltyAccount::factory()->create([
        'store_id' => $store->id,
        'customer_id' => $customer->id,
    ]);

    $rawToken = Str::random(40);
    $tokenHash = hash('sha256', $rawToken);

    $customer->update([
        'email_verification_token_hash' => $tokenHash,
        'email_verification_expires_at' => now()->addMinutes(60),
    ]);

    $response = $this->get(route('customer.email.verification.verify', ['token' => $rawToken]) . '?card=' . $account->public_token);

    $response->assertRedirect(route('card.show', ['public_token' => $account->public_token]));
    $response->assertSessionHas('message', 'Email verified successfully!');

    $customer->refresh();
    expect($customer->email_verified_at)->not->toBeNull();
    expect($customer->email_verification_token_hash)->toBeNull();
    expect($customer->email_verification_expires_at)->toBeNull();
});

test('cannot send if customer email is null', function () {
    $store = Store::factory()->create();
    $customer = Customer::factory()->create(['email' => null]);
    $account = LoyaltyAccount::factory()->create([
        'store_id' => $store->id,
        'customer_id' => $customer->id,
    ]);

    $response = $this->post(route('customer.email.verification.send', ['public_token' => $account->public_token]));

    $response->assertRedirect();
    $response->assertSessionHasErrors('email');
    $response->assertSessionMissing('message');
});

test('expired token fails gracefully', function () {
    $store = Store::factory()->create();
    $customer = Customer::factory()->create(['email' => 'test@example.com']);
    $account = LoyaltyAccount::factory()->create([
        'store_id' => $store->id,
        'customer_id' => $customer->id,
    ]);

    $rawToken = Str::random(40);
    $tokenHash = hash('sha256', $rawToken);

    $customer->update([
        'email_verification_token_hash' => $tokenHash,
        'email_verification_expires_at' => now()->subMinutes(1), // Expired
    ]);

    $response = $this->get(route('customer.email.verification.verify', ['token' => $rawToken]));

    $response->assertRedirect('/');
    $response->assertSessionHasErrors('email');

    $customer->refresh();
    expect($customer->email_verified_at)->toBeNull();
});

test('already verified customer shows success message', function () {
    $store = Store::factory()->create();
    $customer = Customer::factory()->create([
        'email' => 'test@example.com',
        'email_verified_at' => now(),
    ]);
    $account = LoyaltyAccount::factory()->create([
        'store_id' => $store->id,
        'customer_id' => $customer->id,
    ]);

    $response = $this->post(route('customer.email.verification.send', ['public_token' => $account->public_token]));

    $response->assertRedirect();
    $response->assertSessionHas('message', 'Email already verified.');
});

test('resend cooldown is enforced', function () {
    Mail::fake();

    $store = Store::factory()->create();
    $customer = Customer::factory()->create([
        'email' => 'test@example.com',
        'email_verification_sent_at' => now()->subSeconds(30), // 30 seconds ago
    ]);
    $account = LoyaltyAccount::factory()->create([
        'store_id' => $store->id,
        'customer_id' => $customer->id,
    ]);

    $response = $this->post(route('customer.email.verification.send', ['public_token' => $account->public_token]));

    $response->assertRedirect();
    $response->assertSessionHasErrors('email');
    $response->assertSessionMissing('message');

    Mail::assertNothingQueued();
});

test('verify redirects to card when card query param provided', function () {
    $store = Store::factory()->create();
    $customer = Customer::factory()->create(['email' => 'test@example.com']);
    $account = LoyaltyAccount::factory()->create([
        'store_id' => $store->id,
        'customer_id' => $customer->id,
    ]);

    $rawToken = Str::random(40);
    $tokenHash = hash('sha256', $rawToken);

    $customer->update([
        'email_verification_token_hash' => $tokenHash,
        'email_verification_expires_at' => now()->addMinutes(60),
    ]);

    $response = $this->get(route('customer.email.verification.verify', ['token' => $rawToken]) . '?card=' . $account->public_token);

    $response->assertRedirect(route('card.show', ['public_token' => $account->public_token]));
});

test('verify redirects to home when no card query param and no loyalty accounts', function () {
    $customer = Customer::factory()->create(['email' => 'test@example.com']);

    $rawToken = Str::random(40);
    $tokenHash = hash('sha256', $rawToken);

    $customer->update([
        'email_verification_token_hash' => $tokenHash,
        'email_verification_expires_at' => now()->addMinutes(60),
    ]);

    $response = $this->get(route('customer.email.verification.verify', ['token' => $rawToken]));

    $response->assertRedirect('/');
    $response->assertSessionHas('message', 'Email verified successfully!');
});


