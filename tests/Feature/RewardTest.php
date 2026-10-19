<?php

use App\Models\Customer;
use App\Models\LoyaltyAccount;
use App\Models\Store;
use App\Models\User;

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
        'redeem_token' => 'testredeemtoken',
        'verified_at' => now(),
    ]);

    $response = $this->actingAs($user)->postJson('/redeem', [
        'store_id' => $store->id,
        'token' => 'testredeemtoken',
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
        'redeem_token' => 'oldtoken',
        'verified_at' => now(),
    ]);

    $response = $this->actingAs($user)->postJson('/redeem', [
        'store_id' => $store->id,
        'token' => 'REDEEM:oldtoken',
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
    $response->assertSee('is ready');
    $response->assertSee('Show this code to staff to collect stamps or redeem rewards.');
    $response->assertDontSee('redeem-qr-container')->assertDontSee('showRedeemModal');
    expect(substr_count($response->getContent(), 'id="stamp-qr-container"'))->toBe(1);
    // Internal redemption token remains available for authenticated staff.
    $response->assertViewHas('account', function ($viewAccount) {
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
        'redeem_token' => 'testredeemtoken',
        'verified_at' => now(),
    ]);

    $response = $this->actingAs($user)->postJson('/redeem', [
        'store_id' => $store->id,
        'token' => 'REDEEM:testredeemtoken',
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
        'redeem_token' => 'testredeemtoken',
        'verified_at' => now(),
    ]);

    $response = $this->actingAs($user)->postJson('/redeem', [
        'store_id' => $store->id,
        'token' => 'REDEEM:testredeemtoken',
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

test('one card QR supports staff stamp and verified redemption with safe retries', function (string $prefix) {
    Illuminate\Support\Facades\Queue::fake();
    $owner = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $owner->id, 'reward_target' => 3]);
    $card = LoyaltyAccount::factory()->create(['store_id' => $store->id, 'stamp_count' => 0, 'reward_balance' => 0, 'signup_requires_verification' => true, 'verified_at' => null]);
    $this->actingAs($owner);
    $scan = ['store_id' => $store->id, 'token' => 'LA:'.$card->public_token];
    $this->postJson($prefix.'/scanner/preview', $scan)->assertOk()->assertJsonPath('can_stamp', true);
    $this->postJson($prefix.'/stamp', ['store_id' => $store->id, 'token' => $card->public_token, 'count' => 3, 'idempotency_key' => 'single-qr-stamp'])->assertOk();
    $preview = $this->postJson($prefix.'/scanner/preview', $scan)->assertOk()->assertJsonPath('has_rewards', true)->assertJsonPath('can_redeem', true);
    $redeem = ['store_id' => $store->id, 'token' => $preview->json('redeem_token'), 'idempotency_key' => 'single-qr-redeem'];
    $this->postJson($prefix.'/redeem', $redeem)->assertStatus(422)->assertJsonPath('status', 'verification_required');
    $card->update(['verified_at' => now()]);
    $this->postJson($prefix.'/redeem', $redeem)->assertOk();
    $this->postJson($prefix.'/redeem', $redeem)->assertOk()->assertJsonPath('status', 'duplicate');
    expect($card->fresh()->reward_balance)->toBe(0);
    $this->postJson($prefix.'/scanner/preview', $scan)->assertOk()->assertJsonPath('has_rewards', false);
    $this->actingAs(User::factory()->create())->postJson($prefix.'/scanner/preview', $scan)->assertForbidden();
})->with(['web' => '', 'mobile API' => '/api/v1']);

test('browser refresh snapshot exposes card revision and card scoped verification', function () {
    $account = LoyaltyAccount::factory()->create(['version' => 7, 'verified_at' => null]);
    $this->getJson('/api/card/'.$account->public_token)->assertOk()->assertJsonPath('version', 7)->assertJsonPath('verified', false);
    $account->update(['verified_at' => now()]);
    $this->getJson('/api/card/'.$account->public_token)->assertOk()->assertJsonPath('version', 7)->assertJsonPath('verified', true);
    $this->get('/c/'.$account->public_token)->assertOk()->assertSee('js/card-refresh.js')->assertSee('watchCard');
});
