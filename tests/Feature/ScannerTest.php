<?php

use App\Models\Customer;
use App\Models\LoyaltyAccount;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Carbon;

test('scanner page is auth protected', function () {
    $response = $this->get('/scanner');
    $response->assertRedirect('/login');
});

test('scanner page loads for authenticated user', function () {
    $user = User::factory()->create();
    $response = $this->actingAs($user)->get('/scanner');
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

    $response = $this->actingAs($attacker)->postJson('/stamp', [
        'store_id' => $store->id,
        'token' => $account->public_token,
    ]);

    $response->assertForbidden();
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
