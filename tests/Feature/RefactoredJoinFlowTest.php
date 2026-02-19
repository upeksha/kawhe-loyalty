<?php

use App\Models\Store;
use App\Models\User;
use App\Models\Customer;
use App\Models\LoyaltyAccount;
use Illuminate\Support\Facades\Notification;
use App\Notifications\VerifyLoyaltyAccount;
use Illuminate\Support\Facades\URL;

test('short join URL /j/{code} redirects to join flow', function () {
    $store = Store::factory()->create();
    $response = $this->get(route('join.short', ['code' => $store->join_short_code]));
    $response->assertRedirect(route('join.index', ['slug' => $store->slug, 't' => $store->join_token]));
});

test('short join URL is case-insensitive', function () {
    $store = Store::factory()->create(['join_short_code' => 'ABC12X']);
    $response = $this->get(route('join.short', ['code' => 'abc12x']));
    $response->assertRedirect(route('join.index', ['slug' => $store->slug, 't' => $store->join_token]));
});

test('landing page validates slug and token', function () {
    $merchant = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $merchant->id]);

    // Valid slug + token
    $response = $this->get(route('join.index', ['slug' => $store->slug, 't' => $store->join_token]));
    $response->assertOk();
    $response->assertViewIs('join.landing');

    // Invalid token
    $response = $this->get(route('join.index', ['slug' => $store->slug, 't' => 'invalid']));
    $response->assertNotFound();
});

test('new join flow still works and redirects to card', function () {
    $merchant = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $merchant->id]);

    $response = $this->post(route('join.store', ['slug' => $store->slug, 't' => $store->join_token]), [
        'name' => 'New Customer',
        'email' => 'new@example.com',
    ]);

    $account = LoyaltyAccount::first();
    $response->assertRedirect(route('card.show', ['public_token' => $account->public_token]));
    expect($account->customer->email)->toBe('new@example.com');
    expect($account->verified_at)->toBeNull();
});

test('returning lookup is store-scoped and redirects directly', function () {
    $merchant = User::factory()->create();
    $storeA = Store::factory()->create(['user_id' => $merchant->id, 'name' => 'Store A']);
    $storeB = Store::factory()->create(['user_id' => $merchant->id, 'name' => 'Store B']);

    $customer = Customer::factory()->create(['email' => 'returning@example.com']);
    $accountA = LoyaltyAccount::factory()->create([
        'store_id' => $storeA->id,
        'customer_id' => $customer->id,
        'public_token' => 'token-a'
    ]);

    // Try lookup in Store B (should not find Store A card)
    $response = $this->post(route('join.lookup', ['slug' => $storeB->slug, 't' => $storeB->join_token]), [
        'email' => 'returning@example.com',
    ]);
    $response->assertSessionHasErrors(['email']);

    // Successful lookup in Store A
    $response = $this->post(route('join.lookup', ['slug' => $storeA->slug, 't' => $storeA->join_token]), [
        'email' => 'returning@example.com',
    ]);
    $response->assertRedirect(route('card.show', ['public_token' => 'token-a']));
});

test('verification flow sends email and marks as verified', function () {
    Notification::fake();

    $account = LoyaltyAccount::factory()->create();

    $response = $this->post(route('card.verification.send', ['public_token' => $account->public_token]));
    $response->assertSessionHas('verified_sent');

    Notification::assertSentTo($account, VerifyLoyaltyAccount::class);

    // Get the verification link logic
    $url = URL::signedRoute('card.verification.verify', [
        'public_token' => $account->public_token,
        'id' => $account->id,
        'hash' => sha1($account->customer->email),
    ]);

    $response = $this->get($url);
    $response->assertRedirect(route('card.show', ['public_token' => $account->public_token]));
    $response->assertSessionHas('verified_success');
    
    expect($account->fresh()->verified_at)->not->toBeNull();
});

test('redemption requires verified account', function () {
    $merchant = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $merchant->id]);
    $account = LoyaltyAccount::factory()->create([
        'store_id' => $store->id,
        'verified_at' => null,
        'stamp_count' => 10, // Assuming 10 is target
        'reward_balance' => 1,
        'reward_available_at' => now(),
        'redeem_token' => 'redeem123',
    ]);

    $this->actingAs($merchant);

    // Try redeem unverified (token normalized: REDEEM:redeem123 -> redeem123)
    $response = $this->postJson(route('redeem.store'), [
        'token' => 'REDEEM:redeem123',
        'store_id' => $store->id,
    ]);
    $response->assertStatus(422);
    $response->assertJsonPath('status', 'verification_required');
    $response->assertJsonPath('public_token', $account->public_token);
    $response->assertJsonFragment(['message' => 'You must verify your email address before you can redeem rewards. Please check your loyalty card page for verification options.']);

    // Verify and try again
    $account->update(['verified_at' => now()]);

    $response = $this->postJson(route('redeem.store'), [
        'token' => 'REDEEM:redeem123',
        'store_id' => $store->id,
    ]);
    $response->assertOk();
    expect($account->fresh()->reward_redeemed_at)->not->toBeNull();
});
