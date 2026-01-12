<?php

use App\Models\Store;
use App\Models\User;
use App\Models\Customer;
use App\Models\LoyaltyAccount;

test('customer can join two different stores and get distinct loyalty accounts', function () {
    $merchant = User::factory()->create();
    $storeA = Store::factory()->create(['user_id' => $merchant->id, 'name' => 'Store A']);
    $storeB = Store::factory()->create(['user_id' => $merchant->id, 'name' => 'Store B']);

    $customerData = [
        'name' => 'Test Customer',
        'email' => 'test@example.com',
        'phone' => '1234567890',
    ];

    // Join Store A
    $responseA = $this->post(route('join.store', ['slug' => $storeA->slug, 't' => $storeA->join_token]), $customerData);
    $responseA->assertRedirect();
    
    $accountA = LoyaltyAccount::where('store_id', $storeA->id)->first();
    expect($accountA)->not->toBeNull();

    // Join Store B
    $responseB = $this->post(route('join.store', ['slug' => $storeB->slug, 't' => $storeB->join_token]), $customerData);
    $responseB->assertRedirect();

    $accountB = LoyaltyAccount::where('store_id', $storeB->id)->first();
    expect($accountB)->not->toBeNull();
    
    // Assert loyalty_accounts count is 2
    expect(LoyaltyAccount::count())->toBe(2);
    expect($accountA->id)->not->toBe($accountB->id);
    expect($accountA->public_token)->not->toBe($accountB->public_token);
});

test('stamping works for the correct store and is blocked for mismatching store', function () {
    $merchant = User::factory()->create();
    $storeA = Store::factory()->create(['user_id' => $merchant->id, 'name' => 'Store A']);
    $storeB = Store::factory()->create(['user_id' => $merchant->id, 'name' => 'Store B']);

    $accountA = LoyaltyAccount::factory()->create(['store_id' => $storeA->id]);
    $accountB = LoyaltyAccount::factory()->create(['store_id' => $storeB->id]);

    $this->actingAs($merchant);

    // B) Stamping works for correct store
    $response = $this->postJson(route('stamp.store'), [
        'token' => "LA:{$accountB->public_token}",
        'store_id' => $storeB->id,
        'count' => 1
    ]);

    $response->assertOk();
    $response->assertJsonPath('success', true);
    expect($accountB->fresh()->stamp_count)->toBe(1);

    // C) Store mismatch now auto-detects and switches (merchant owns both stores)
    $responseMismatch = $this->postJson(route('stamp.store'), [
        'token' => $accountA->public_token,
        'store_id' => $storeB->id,
        'count' => 1
    ]);

    // With auto-detection, wrong store selected should auto-switch to correct store
    $responseMismatch->assertOk();
    $responseMismatch->assertJson([
        'success' => true,
        'store_switched' => true,
        'store_id_used' => $storeA->id,
    ]);
    expect($accountA->fresh()->stamp_count)->toBe(1);
});

test('token parsing handles prefix and whitespace', function () {
    $merchant = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $merchant->id]);
    $account = LoyaltyAccount::factory()->create(['store_id' => $store->id]);

    $this->actingAs($merchant);

    // Test with LA: prefix and whitespace
    $response = $this->postJson(route('stamp.store'), [
        'token' => "  LA:{$account->public_token}  ",
        'store_id' => $store->id,
        'count' => 1
    ]);

    $response->assertOk();
    expect($account->fresh()->stamp_count)->toBe(1);
});
