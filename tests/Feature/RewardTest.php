<?php

use App\Models\Customer;
use App\Models\LoyaltyAccount;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Carbon;

test('reward becomes available when target reached', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $user->id, 'reward_target' => 5]);
    $customer = Customer::factory()->create();
    $account = LoyaltyAccount::create([
        'store_id' => $store->id,
        'customer_id' => $customer->id,
        'stamp_count' => 4,
    ]);

    $this->actingAs($user)->postJson('/stamp', [
        'store_id' => $store->id,
        'token' => $account->public_token,
    ]);

    $account->refresh();
    $this->assertEquals(0, $account->stamp_count); // 5 % 5 = 0
    $this->assertEquals(1, $account->reward_balance); // floor(5 / 5) = 1
    $this->assertNotNull($account->reward_available_at);
    $this->assertNotNull($account->redeem_token);
});

test('merchant can redeem a reward', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $user->id, 'reward_target' => 5]);
    $customer = Customer::factory()->create([
        'email' => 'test@example.com',
        'email_verified_at' => now(),
    ]);
    $account = LoyaltyAccount::create([
        'store_id' => $store->id,
        'customer_id' => $customer->id,
        'stamp_count' => 0,
        'reward_balance' => 1,
        'reward_available_at' => now(),
        'redeem_token' => 'test-redeem-token',
    ]);

    $response = $this->actingAs($user)->postJson('/redeem', [
        'store_id' => $store->id,
        'token' => 'test-redeem-token',
    ]);

    $response->assertOk();
    $response->assertJson(['success' => true]);

    $account->refresh();
    $this->assertNotNull($account->reward_redeemed_at);
    $this->assertNull($account->redeem_token);
    $this->assertEquals(0, $account->stamp_count); // Unchanged
    $this->assertEquals(0, $account->reward_balance); // Decremented to 0
    $this->assertNull($account->reward_available_at);

    $this->assertDatabaseHas('stamp_events', [
        'loyalty_account_id' => $account->id,
        'type' => 'redeem',
    ]);
});

test('cannot redeem when no rewards available', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $user->id]);
    $customer = Customer::factory()->create(['email_verified_at' => now()]);
    $account = LoyaltyAccount::create([
        'store_id' => $store->id,
        'customer_id' => $customer->id,
        'reward_balance' => 0,
        'redeem_token' => 'old-token',
    ]);

    $response = $this->actingAs($user)->postJson('/redeem', [
        'store_id' => $store->id,
        'token' => 'REDEEM:old-token',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['token']);
});

test('card page shows redeem qr when available', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $user->id]);
    $customer = Customer::factory()->create([
        'email' => 'test@example.com',
        'email_verified_at' => now(),
    ]);
    $account = LoyaltyAccount::create([
        'store_id' => $store->id,
        'customer_id' => $customer->id,
        'stamp_count' => 0,
        'reward_balance' => 1,
        'reward_available_at' => now(),
        'redeem_token' => 'visible-redeem-token',
    ]);

    $response = $this->get(route('card.show', ['public_token' => $account->public_token]));

    $response->assertOk();
    // Use assertSee with false to avoid escaping issues with SVG content, 
    // or just check for the reward text as proxy
    $response->assertSee('Reward Unlocked!');
    // Verify the redeem token is passed to the QR generator
    $response->assertViewHas('account', function ($viewAccount) use ($account) {
        return $viewAccount->redeem_token === 'visible-redeem-token';
    });
});

test('multi-reward: stamping 12 stamps on 5-target card earns 2 rewards with 2 remainder', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $user->id, 'reward_target' => 5]);
    $customer = Customer::factory()->create();
    $account = LoyaltyAccount::create([
        'store_id' => $store->id,
        'customer_id' => $customer->id,
        'stamp_count' => 0,
        'reward_balance' => 0,
    ]);

    $response = $this->actingAs($user)->postJson('/stamp', [
        'store_id' => $store->id,
        'token' => $account->public_token,
        'count' => 12,
    ]);

    $response->assertOk();
    $account->refresh();
    
    expect($account->stamp_count)->toBe(2); // 12 % 5 = 2
    expect($account->reward_balance)->toBe(2); // floor(12 / 5) = 2
    expect($account->reward_available_at)->not->toBeNull();
    expect($account->redeem_token)->not->toBeNull();
});

test('multi-reward: redeem one reward decrements balance but keeps stamp_count', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $user->id, 'reward_target' => 5]);
    $customer = Customer::factory()->create(['email_verified_at' => now()]);
    $account = LoyaltyAccount::create([
        'store_id' => $store->id,
        'customer_id' => $customer->id,
        'stamp_count' => 2,
        'reward_balance' => 2,
        'reward_available_at' => now(),
        'redeem_token' => 'test-redeem-token',
    ]);

    $response = $this->actingAs($user)->postJson('/redeem', [
        'store_id' => $store->id,
        'token' => 'REDEEM:test-redeem-token',
    ]);

    $response->assertOk();
    $account->refresh();
    
    expect($account->reward_balance)->toBe(1); // Decremented by 1
    expect($account->stamp_count)->toBe(2); // Unchanged
    expect($account->reward_available_at)->not->toBeNull(); // Still available
    expect($account->redeem_token)->not->toBeNull(); // Token still valid
});

test('multi-reward: redeem second reward clears availability when balance reaches 0', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $user->id, 'reward_target' => 5]);
    $customer = Customer::factory()->create(['email_verified_at' => now()]);
    $account = LoyaltyAccount::create([
        'store_id' => $store->id,
        'customer_id' => $customer->id,
        'stamp_count' => 2,
        'reward_balance' => 1, // Last reward
        'reward_available_at' => now(),
        'redeem_token' => 'test-redeem-token',
    ]);

    $response = $this->actingAs($user)->postJson('/redeem', [
        'store_id' => $store->id,
        'token' => 'REDEEM:test-redeem-token',
    ]);

    $response->assertOk();
    $account->refresh();
    
    expect($account->reward_balance)->toBe(0);
    expect($account->stamp_count)->toBe(2); // Unchanged
    expect($account->reward_available_at)->toBeNull(); // Cleared
    expect($account->redeem_token)->toBeNull(); // Cleared
});

test('multi-reward: old single-reward scenario still works', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $user->id, 'reward_target' => 5]);
    $customer = Customer::factory()->create();
    $account = LoyaltyAccount::create([
        'store_id' => $store->id,
        'customer_id' => $customer->id,
        'stamp_count' => 0,
        'reward_balance' => 0,
    ]);

    $response = $this->actingAs($user)->postJson('/stamp', [
        'store_id' => $store->id,
        'token' => $account->public_token,
        'count' => 5,
    ]);

    $response->assertOk();
    $account->refresh();
    
    expect($account->stamp_count)->toBe(0); // 5 % 5 = 0
    expect($account->reward_balance)->toBe(1); // floor(5 / 5) = 1
    expect($account->reward_available_at)->not->toBeNull();
    expect($account->redeem_token)->not->toBeNull();
});

test('multi-reward: idempotency still works with new math', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $user->id, 'reward_target' => 5]);
    $customer = Customer::factory()->create();
    $account = LoyaltyAccount::create([
        'store_id' => $store->id,
        'customer_id' => $customer->id,
        'stamp_count' => 0,
        'reward_balance' => 0,
    ]);

    $idempotencyKey = 'test-idempotency-key-123';

    // First request
    $response1 = $this->actingAs($user)->postJson('/stamp', [
        'store_id' => $store->id,
        'token' => $account->public_token,
        'count' => 12,
        'idempotency_key' => $idempotencyKey,
    ]);

    $response1->assertOk();
    $account->refresh();
    $stampCountAfterFirst = $account->stamp_count;
    $rewardBalanceAfterFirst = $account->reward_balance;

    // Duplicate request
    $response2 = $this->actingAs($user)->postJson('/stamp', [
        'store_id' => $store->id,
        'token' => $account->public_token,
        'count' => 12,
        'idempotency_key' => $idempotencyKey,
    ]);

    $response2->assertOk();
    $response2->assertJson(['message' => 'Already processed']);
    $account->refresh();
    
    expect($account->stamp_count)->toBe($stampCountAfterFirst);
    expect($account->reward_balance)->toBe($rewardBalanceAfterFirst);
});
