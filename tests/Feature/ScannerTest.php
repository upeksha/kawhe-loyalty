<?php

use App\Models\Customer;
use App\Models\LoyaltyAccount;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Carbon;

test('scanner page is auth protected', function () {
    $response = $this->get('/merchant/scanner');
    $response->assertRedirect('/login');
});

test('scanner page loads for authenticated user', function () {
    $user = User::factory()->create();
    Store::factory()->create(['user_id' => $user->id]); // User needs at least one store
    $response = $this->actingAs($user)->get('/merchant/scanner');
    $response->assertOk();
});

test('merchant can stamp a loyalty account', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $user->id]);
    $customer = Customer::factory()->create();
    $account = LoyaltyAccount::create([
        'store_id' => $store->id,
        'customer_id' => $customer->id,
    ]);

    $response = $this->actingAs($user)->postJson('/stamp', [
        'store_id' => $store->id,
        'token' => $account->public_token,
    ]);

    $response->assertOk();
    $response->assertJson(['success' => true]);

    $account->refresh();
    $this->assertEquals(1, $account->stamp_count);
    $this->assertNotNull($account->last_stamped_at);

    $this->assertDatabaseHas('stamp_events', [
        'loyalty_account_id' => $account->id,
        'store_id' => $store->id,
        'user_id' => $user->id,
        'count' => 1,
    ]);
});

test('merchant can stamp multiple times at once', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $user->id]);
    $customer = Customer::factory()->create();
    $account = LoyaltyAccount::create([
        'store_id' => $store->id,
        'customer_id' => $customer->id,
    ]);

    $response = $this->actingAs($user)->postJson('/stamp', [
        'store_id' => $store->id,
        'token' => $account->public_token,
        'count' => 3,
    ]);

    $response->assertOk();
    $response->assertJson(['success' => true]);

    $account->refresh();
    $this->assertEquals(3, $account->stamp_count);

    $this->assertDatabaseHas('stamp_events', [
        'loyalty_account_id' => $account->id,
        'store_id' => $store->id,
        'user_id' => $user->id,
        'count' => 3,
    ]);
});

test('merchant cannot stamp loyalty account from another store', function () {
    $owner = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $owner->id]);
    $customer = Customer::factory()->create();
    $account = LoyaltyAccount::create([
        'store_id' => $store->id,
        'customer_id' => $customer->id,
    ]);

    $attacker = User::factory()->create();
    Store::factory()->create(['user_id' => $attacker->id]); // Attacker has their own store

    $response = $this->actingAs($attacker)->postJson('/stamp', [
        'store_id' => $store->id,
        'token' => $account->public_token,
    ]);

    // Now returns 422 validation error instead of 403, which is correct
    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['token']);
    $this->assertStringContainsString('do not have access', $response->json('errors.token.0'));
});

test('cooldown prevents double stamping', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $user->id]);
    $customer = Customer::factory()->create();
    $account = LoyaltyAccount::create([
        'store_id' => $store->id,
        'customer_id' => $customer->id,
    ]);

    // First stamp
    $this->actingAs($user)->postJson('/stamp', [
        'store_id' => $store->id,
        'token' => $account->public_token,
    ])->assertOk();

    // Immediate second stamp
    $response = $this->actingAs($user)->postJson('/stamp', [
        'store_id' => $store->id,
        'token' => $account->public_token,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['token']);
    
    $account->refresh();
    $this->assertEquals(1, $account->stamp_count);
});

test('can stamp again after cooldown', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $user->id]);
    $customer = Customer::factory()->create();
    $account = LoyaltyAccount::create([
        'store_id' => $store->id,
        'customer_id' => $customer->id,
        'last_stamped_at' => Carbon::now()->subSeconds(31),
        'stamp_count' => 1,
    ]);

    $response = $this->actingAs($user)->postJson('/stamp', [
        'store_id' => $store->id,
        'token' => $account->public_token,
    ]);

    $response->assertOk();
    $account->refresh();
    $this->assertEquals(2, $account->stamp_count);
});

test('auto-detects store from token when wrong store selected', function () {
    $merchant = User::factory()->create();
    $storeA = Store::factory()->create(['user_id' => $merchant->id, 'name' => 'Store A']);
    $storeB = Store::factory()->create(['user_id' => $merchant->id, 'name' => 'Store B']);
    
    $customer = Customer::factory()->create();
    $account = LoyaltyAccount::create([
        'store_id' => $storeB->id, // Card belongs to Store B
        'customer_id' => $customer->id,
    ]);

    // Merchant has Store A selected in UI, but scans Store B card
    $response = $this->actingAs($merchant)->postJson('/stamp', [
        'store_id' => $storeA->id, // Wrong store selected
        'token' => $account->public_token,
    ]);

    $response->assertOk();
    $response->assertJson([
        'success' => true,
        'store_switched' => true,
        'store_id_used' => $storeB->id,
        'store_name_used' => 'Store B',
    ]);
    
    $account->refresh();
    $this->assertEquals(1, $account->stamp_count);
});

test('auto-detects store from token when no store selected', function () {
    $merchant = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $merchant->id]);
    
    $customer = Customer::factory()->create();
    $account = LoyaltyAccount::create([
        'store_id' => $store->id,
        'customer_id' => $customer->id,
    ]);

    // No store_id provided - backend should auto-detect
    $response = $this->actingAs($merchant)->postJson('/stamp', [
        'token' => $account->public_token,
    ]);

    $response->assertOk();
    $response->assertJson([
        'success' => true,
        'store_id_used' => $store->id,
        'store_switched' => false,
    ]);
    
    $account->refresh();
    $this->assertEquals(1, $account->stamp_count);
});

test('blocks stamping when card belongs to another merchants store', function () {
    $merchantA = User::factory()->create();
    $merchantB = User::factory()->create();
    
    $storeA = Store::factory()->create(['user_id' => $merchantA->id, 'name' => 'Store A']);
    $storeB = Store::factory()->create(['user_id' => $merchantB->id, 'name' => 'Store B']);
    
    $customer = Customer::factory()->create();
    $account = LoyaltyAccount::create([
        'store_id' => $storeB->id, // Card belongs to Merchant B's store
        'customer_id' => $customer->id,
    ]);

    // Merchant A tries to stamp Merchant B's card
    $response = $this->actingAs($merchantA)->postJson('/stamp', [
        'store_id' => $storeA->id,
        'token' => $account->public_token,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['token']);
    $this->assertStringContainsString('do not have access', $response->json('errors.token.0'));
    
    $account->refresh();
    $this->assertEquals(0, $account->stamp_count); // Should not be stamped
});

test('backwards compatibility - correct store selected works as before', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $user->id]);
    $customer = Customer::factory()->create();
    $account = LoyaltyAccount::create([
        'store_id' => $store->id,
        'customer_id' => $customer->id,
    ]);

    $response = $this->actingAs($user)->postJson('/stamp', [
        'store_id' => $store->id, // Correct store
        'token' => $account->public_token,
    ]);

    $response->assertOk();
    $response->assertJson([
        'success' => true,
        'store_switched' => false,
        'store_id_used' => $store->id,
    ]);
    
    $account->refresh();
    $this->assertEquals(1, $account->stamp_count);
});

test('cooldown still works with auto-detected store', function () {
    $merchant = User::factory()->create();
    $storeA = Store::factory()->create(['user_id' => $merchant->id]);
    $storeB = Store::factory()->create(['user_id' => $merchant->id]);
    
    $customer = Customer::factory()->create();
    $account = LoyaltyAccount::create([
        'store_id' => $storeB->id,
        'customer_id' => $customer->id,
    ]);

    // First stamp with wrong store selected
    $this->actingAs($merchant)->postJson('/stamp', [
        'store_id' => $storeA->id, // Wrong store
        'token' => $account->public_token,
    ])->assertOk();

    // Immediate second stamp should be blocked by cooldown
    $response = $this->actingAs($merchant)->postJson('/stamp', [
        'store_id' => $storeA->id,
        'token' => $account->public_token,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['token']);
    
    $account->refresh();
    $this->assertEquals(1, $account->stamp_count); // Should still be 1
});

test('idempotency still works with auto-detected store', function () {
    $merchant = User::factory()->create();
    $storeA = Store::factory()->create(['user_id' => $merchant->id]);
    $storeB = Store::factory()->create(['user_id' => $merchant->id]);
    
    $customer = Customer::factory()->create();
    $account = LoyaltyAccount::create([
        'store_id' => $storeB->id,
        'customer_id' => $customer->id,
    ]);

    $idempotencyKey = 'test-idempotency-key-123';

    // First request
    $response1 = $this->actingAs($merchant)->postJson('/stamp', [
        'store_id' => $storeA->id,
        'token' => $account->public_token,
        'idempotency_key' => $idempotencyKey,
    ]);

    $response1->assertOk();
    $account->refresh();
    $this->assertEquals(1, $account->stamp_count);

    // Duplicate request with same idempotency key
    $response2 = $this->actingAs($merchant)->postJson('/stamp', [
        'store_id' => $storeA->id,
        'token' => $account->public_token,
        'idempotency_key' => $idempotencyKey,
    ]);

    $response2->assertOk();
    $response2->assertJson(['message' => 'Already processed']);
    
    $account->refresh();
    $this->assertEquals(1, $account->stamp_count); // Should still be 1, not 2
});
