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
    $this->assertEquals(5, $account->stamp_count);
    $this->assertNotNull($account->reward_available_at);
    $this->assertNotNull($account->redeem_token);
});

test('merchant can redeem a reward', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $user->id, 'reward_target' => 5]);
    $customer = Customer::factory()->create();
    $account = LoyaltyAccount::create([
        'store_id' => $store->id,
        'customer_id' => $customer->id,
        'stamp_count' => 5,
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
    $this->assertEquals(0, $account->stamp_count); // Stamps reset (5 - 5 = 0)
    $this->assertNull($account->reward_available_at);

    $this->assertDatabaseHas('stamp_events', [
        'loyalty_account_id' => $account->id,
        'type' => 'redeem',
    ]);
});

test('cannot redeem twice', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $user->id]);
    $customer = Customer::factory()->create();
    $account = LoyaltyAccount::create([
        'store_id' => $store->id,
        'customer_id' => $customer->id,
        'reward_redeemed_at' => now(),
        'redeem_token' => 'old-token',
    ]);

    $response = $this->actingAs($user)->postJson('/redeem', [
        'store_id' => $store->id,
        'token' => 'old-token',
    ]);

    $response->assertStatus(422);
});

test('card page shows redeem qr when available', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $user->id]);
    $customer = Customer::factory()->create();
    $account = LoyaltyAccount::create([
        'store_id' => $store->id,
        'customer_id' => $customer->id,
        'stamp_count' => 10,
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
