<?php

use App\Models\Customer;
use App\Models\LoyaltyAccount;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Carbon;

test('scanner page is auth protected', function () {
    $response = $this->get('/merchant/scanner');
    $response->assertRedirect('/merchant/login');
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

    // Immediate second stamp (within cooldown - should be blocked with 409 cooldown)
    $response = $this->actingAs($user)->postJson('/stamp', [
        'store_id' => $store->id,
        'token' => $account->public_token,
    ]);

    // Blocked by cooldown (30s); returns 409 so double stamp is prevented
    $response->assertStatus(409);
    $response->assertJson(['status' => 'cooldown', 'success' => false]);

    $account->refresh();
    $this->assertEquals(1, $account->stamp_count); // Should still be 1
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

test('cooldown returns structured response allowing override', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $user->id]);
    $customer = Customer::factory()->create();
    $account = LoyaltyAccount::create([
        'store_id' => $store->id,
        'customer_id' => $customer->id,
        'last_stamped_at' => Carbon::now()->subSeconds(12), // 12 seconds ago
        'stamp_count' => 1,
    ]);

    $response = $this->actingAs($user)->postJson('/stamp', [
        'store_id' => $store->id,
        'token' => $account->public_token,
    ]);

    $response->assertStatus(409); // HTTP 409 Conflict
    $response->assertJson([
        'status' => 'cooldown',
        'success' => false,
        'allow_override' => true,
        'next_action' => 'confirm_override',
    ]);
    $response->assertJsonStructure([
        'seconds_since_last',
        'cooldown_seconds',
        'stampCount',
        'rewardBalance',
    ]);
    
    // Should NOT have incremented stamp count
    $account->refresh();
    $this->assertEquals(1, $account->stamp_count);
    
    // Should NOT have created new events/transactions
    $eventCount = \App\Models\StampEvent::where('loyalty_account_id', $account->id)->count();
    $this->assertEquals(0, $eventCount); // No events created yet
});

test('override cooldown allows stamping within cooldown period', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $user->id]);
    $customer = Customer::factory()->create();
    $account = LoyaltyAccount::create([
        'store_id' => $store->id,
        'customer_id' => $customer->id,
        'last_stamped_at' => Carbon::now()->subSeconds(12), // 12 seconds ago
        'stamp_count' => 1,
    ]);

    $response = $this->actingAs($user)->postJson('/stamp', [
        'store_id' => $store->id,
        'token' => $account->public_token,
        'override_cooldown' => true,
    ]);

    $response->assertOk();
    $response->assertJson(['success' => true]);
    $response->assertJsonStructure(['status']); // Should have status field
    
    $account->refresh();
    $this->assertEquals(2, $account->stamp_count);
    
    // Should have created event/transaction
    $this->assertDatabaseHas('stamp_events', [
        'loyalty_account_id' => $account->id,
        'store_id' => $store->id,
        'type' => 'stamp',
    ]);
});

test('server-side idempotency window prevents duplicate stamps within 5 seconds', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $user->id]);
    $customer = Customer::factory()->create();
    $account = LoyaltyAccount::create([
        'store_id' => $store->id,
        'customer_id' => $customer->id,
        'stamp_count' => 0,
    ]);

    // First stamp
    $response1 = $this->actingAs($user)->postJson('/stamp', [
        'store_id' => $store->id,
        'token' => $account->public_token,
        'idempotency_key' => 'key-1', // Different key
    ]);

    $response1->assertOk();
    $account->refresh();
    $stampCountAfterFirst = $account->stamp_count;
    $this->assertEquals(1, $stampCountAfterFirst);
    
    // Wait a moment but still within 5 seconds
    usleep(100000); // 0.1 seconds
    
    // Second stamp with different idempotency key (simulating page reload)
    $response2 = $this->actingAs($user)->postJson('/stamp', [
        'store_id' => $store->id,
        'token' => $account->public_token,
        'idempotency_key' => 'key-2', // Different key - still blocked by cooldown
    ]);

    // Blocked by cooldown (30s) so duplicate stamp is prevented
    $response2->assertStatus(409);
    $response2->assertJson([
        'status' => 'cooldown',
        'success' => false,
    ]);

    $account->refresh();
    $this->assertEquals($stampCountAfterFirst, $account->stamp_count); // Should not have incremented

    // Should only have ONE stamp event
    $eventCount = \App\Models\StampEvent::where('loyalty_account_id', $account->id)->count();
    $this->assertEquals(1, $eventCount);
});

test('override request within 5 seconds still treated as duplicate', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $user->id]);
    $customer = Customer::factory()->create();
    $account = LoyaltyAccount::create([
        'store_id' => $store->id,
        'customer_id' => $customer->id,
        'stamp_count' => 0,
    ]);

    // First stamp
    $response1 = $this->actingAs($user)->postJson('/stamp', [
        'store_id' => $store->id,
        'token' => $account->public_token,
    ]);

    $response1->assertOk();
    $account->refresh();
    $stampCountAfterFirst = $account->stamp_count;
    
    // Wait a moment but still within 5 seconds
    usleep(100000); // 0.1 seconds
    
    // Override request within 5 seconds should still be blocked by idempotency window
    $response2 = $this->actingAs($user)->postJson('/stamp', [
        'store_id' => $store->id,
        'token' => $account->public_token,
        'override_cooldown' => true, // Override cooldown, but idempotency window should block
    ]);

    $response2->assertOk();
    $response2->assertJson([
        'status' => 'duplicate',
        'success' => false,
    ]);
    
    $account->refresh();
    $this->assertEquals($stampCountAfterFirst, $account->stamp_count); // Should not have incremented
});

test('normal stamp increments stamps and logs events', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $user->id]);
    $customer = Customer::factory()->create();
    $account = LoyaltyAccount::create([
        'store_id' => $store->id,
        'customer_id' => $customer->id,
        'stamp_count' => 0,
    ]);

    $response = $this->actingAs($user)->postJson('/stamp', [
        'store_id' => $store->id,
        'token' => $account->public_token,
    ]);

    $response->assertOk();
    $response->assertJson(['status' => 'success', 'success' => true]);
    
    $account->refresh();
    $this->assertEquals(1, $account->stamp_count);
    
    // Should have created event
    $this->assertDatabaseHas('stamp_events', [
        'loyalty_account_id' => $account->id,
        'store_id' => $store->id,
        'type' => 'stamp',
        'count' => 1,
    ]);
    
    // Should have created transaction
    $this->assertDatabaseHas('points_transactions', [
        'loyalty_account_id' => $account->id,
        'store_id' => $store->id,
        'type' => 'earn',
        'points' => 1,
    ]);
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

    // Wait for idempotency window to pass but still within cooldown
    sleep(6); // Wait 6 seconds (past 5s idempotency, but within 30s cooldown)
    
    // Second stamp should return cooldown response
    $response = $this->actingAs($merchant)->postJson('/stamp', [
        'store_id' => $storeA->id,
        'token' => $account->public_token,
    ]);

    $response->assertStatus(409); // HTTP 409 Conflict
    $response->assertJson(['status' => 'cooldown']);
    
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
