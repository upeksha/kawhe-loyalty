<?php

use App\Models\Customer;
use App\Models\LoyaltyAccount;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;

beforeEach(function () {
    $this->withoutMiddleware(VerifyCsrfToken::class);
});

test('preview returns stable not-active response for deleted loyalty account', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $user->id]);
    $customer = Customer::factory()->create();
    $account = LoyaltyAccount::factory()->create([
        'store_id' => $store->id,
        'customer_id' => $customer->id,
    ]);

    $token = $account->public_token;
    $account->delete();

    $this->actingAs($user)
        ->postJson('/scanner/preview', [
            'store_id' => $store->id,
            'token' => $token,
        ])
        ->assertStatus(404)
        ->assertJson([
            'success' => false,
            'code' => 'CARD_NOT_ACTIVE',
            'message' => 'This loyalty card is no longer active. Ask the customer to rejoin or contact the store.',
        ]);
});

test('stamp returns stable not-active response for deleted loyalty account', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $user->id]);
    $customer = Customer::factory()->create();
    $account = LoyaltyAccount::factory()->create([
        'store_id' => $store->id,
        'customer_id' => $customer->id,
    ]);

    $token = $account->public_token;
    $account->delete();

    $this->actingAs($user)
        ->postJson('/stamp', [
            'store_id' => $store->id,
            'token' => $token,
        ])
        ->assertStatus(404)
        ->assertJson([
            'success' => false,
            'code' => 'CARD_NOT_ACTIVE',
            'message' => 'This loyalty card is no longer active. Ask the customer to rejoin or contact the store.',
        ]);
});

test('redeem returns stable not-active response for deleted loyalty account', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $user->id]);
    $customer = Customer::factory()->create();
    $account = LoyaltyAccount::factory()->create([
        'store_id' => $store->id,
        'customer_id' => $customer->id,
        'reward_balance' => 1,
        'redeem_token' => 'redeem-token-1234',
    ]);

    $token = $account->redeem_token;
    $account->delete();

    $this->actingAs($user)
        ->postJson('/redeem', [
            'store_id' => $store->id,
            'token' => $token,
            'quantity' => 1,
        ])
        ->assertStatus(404)
        ->assertJson([
            'success' => false,
            'code' => 'CARD_NOT_ACTIVE',
            'message' => 'This loyalty card is no longer active. Ask the customer to rejoin or contact the store.',
        ]);
});
