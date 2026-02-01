<?php

/**
 * Safety tests for 4-char manual entry code: ensure stamping and redeeming
 * never affect the wrong store or wrong card (cross-store / wrong-card isolation).
 */

use App\Models\Customer;
use App\Models\LoyaltyAccount;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

test('stamp with 4-char code and correct store stamps the right account only', function () {
    Queue::fake();
    $user = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $user->id]);
    $customer = Customer::factory()->create();
    $account = LoyaltyAccount::create([
        'store_id' => $store->id,
        'customer_id' => $customer->id,
    ]);
    $account->refresh();
    $this->assertNotNull($account->manual_entry_code);
    $this->assertEquals(4, strlen($account->manual_entry_code));

    $response = $this->actingAs($user)->postJson(route('stamp.store'), [
        'store_id' => $store->id,
        'token' => $account->manual_entry_code,
    ]);

    $response->assertOk();
    $response->assertJson(['success' => true]);
    $account->refresh();
    $this->assertEquals(1, $account->stamp_count);
    $this->assertDatabaseHas('stamp_events', [
        'loyalty_account_id' => $account->id,
        'store_id' => $store->id,
        'user_id' => $user->id,
        'count' => 1,
    ]);
});

test('stamp with 4-char code and wrong store does not find card (no cross-store stamp)', function () {
    $user = User::factory()->create();
    $storeA = Store::factory()->create(['user_id' => $user->id]);
    $storeB = Store::factory()->create(['user_id' => $user->id]);
    $customer = Customer::factory()->create();
    $accountAtA = LoyaltyAccount::create([
        'store_id' => $storeA->id,
        'customer_id' => $customer->id,
    ]);
    $accountAtA->refresh();
    $code = $accountAtA->manual_entry_code;

    // Merchant selects Store B but enters the code for the card at Store A.
    // Lookup is (store_id=B, manual_entry_code=code). No account at Store B has that code.
    $response = $this->actingAs($user)->postJson(route('stamp.store'), [
        'store_id' => $storeB->id,
        'token' => $code,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['token']);
    $accountAtA->refresh();
    $this->assertEquals(0, $accountAtA->stamp_count);
});

test('stamp with 4-char code without store_id does not use 4-char lookup (not found)', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $user->id]);
    $customer = Customer::factory()->create();
    $account = LoyaltyAccount::create([
        'store_id' => $store->id,
        'customer_id' => $customer->id,
    ]);
    $account->refresh();
    $code = $account->manual_entry_code;

    // No store_id: we skip 4-char lookup and try public_token = "A3CX" (4 chars). No account has that as public_token.
    $response = $this->actingAs($user)->postJson(route('stamp.store'), [
        'token' => $code,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['token']);
    $account->refresh();
    $this->assertEquals(0, $account->stamp_count);
});

test('stamp with 4-char code and other merchant store_id is denied by validateStaffAccess', function () {
    $ownerA = User::factory()->create();
    $storeA = Store::factory()->create(['user_id' => $ownerA->id]);
    $customer = Customer::factory()->create();
    $accountAtA = LoyaltyAccount::create([
        'store_id' => $storeA->id,
        'customer_id' => $customer->id,
    ]);
    $accountAtA->refresh();
    $code = $accountAtA->manual_entry_code;

    $ownerB = User::factory()->create();
    $storeB = Store::factory()->create(['user_id' => $ownerB->id]);

    // Attacker (ownerB) sends store_id = storeA and token = code to try to stamp store A's card.
    // Lookup (storeA, code) finds accountAtA. Then stampService->validateStaffAccess(accountAtA, ownerB) fails.
    $response = $this->actingAs($ownerB)->postJson(route('stamp.store'), [
        'store_id' => $storeA->id,
        'token' => $code,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['token']);
    $this->assertStringContainsString('do not have access', $response->json('errors.token.0'));
    $accountAtA->refresh();
    $this->assertEquals(0, $accountAtA->stamp_count);
});

test('redeem with 4-char code and correct store redeems the right account only', function () {
    Queue::fake();
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
        'verified_at' => now(),
        'redeem_token' => \Illuminate\Support\Str::random(\App\Models\LoyaltyAccount::REDEEM_TOKEN_LENGTH),
    ]);
    $account->refresh();
    $this->assertNotNull($account->manual_entry_code);

    $response = $this->actingAs($user)->postJson(route('redeem.store'), [
        'store_id' => $store->id,
        'token' => $account->manual_entry_code,
    ]);

    $response->assertOk();
    $response->assertJson(['success' => true]);
    $account->refresh();
    $this->assertEquals(0, $account->reward_balance);
    $this->assertNotNull($account->reward_redeemed_at);
    $this->assertDatabaseHas('stamp_events', [
        'loyalty_account_id' => $account->id,
        'type' => 'redeem',
    ]);
});

test('redeem with 4-char code and wrong store does not redeem other store card', function () {
    $user = User::factory()->create();
    $storeA = Store::factory()->create(['user_id' => $user->id, 'reward_target' => 5]);
    $storeB = Store::factory()->create(['user_id' => $user->id, 'reward_target' => 5]);
    $customerA = Customer::factory()->create(['email_verified_at' => now()]);
    $customerB = Customer::factory()->create(['email_verified_at' => now()]);
    $accountA = LoyaltyAccount::create([
        'store_id' => $storeA->id,
        'customer_id' => $customerA->id,
        'reward_balance' => 1,
        'reward_available_at' => now(),
        'redeem_token' => \Illuminate\Support\Str::random(\App\Models\LoyaltyAccount::REDEEM_TOKEN_LENGTH),
    ]);
    $accountB = LoyaltyAccount::create([
        'store_id' => $storeB->id,
        'customer_id' => $customerB->id,
        'reward_balance' => 1,
        'reward_available_at' => now(),
        'redeem_token' => \Illuminate\Support\Str::random(\App\Models\LoyaltyAccount::REDEEM_TOKEN_LENGTH),
    ]);
    $accountA->refresh();
    $accountB->refresh();
    $codeA = $accountA->manual_entry_code;

    // Merchant selects Store B and enters Store A card's 4-char code.
    // 4-char resolve: (storeB, codeA) finds no account. Token stays codeA. Redeem lookup by redeem_token=codeA finds nothing → 422.
    $response = $this->actingAs($user)->postJson(route('redeem.store'), [
        'store_id' => $storeB->id,
        'token' => $codeA,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['token']);
    $accountA->refresh();
    $accountB->refresh();
    $this->assertEquals(1, $accountA->reward_balance);
    $this->assertEquals(1, $accountB->reward_balance);
});

test('full token stamp still enforced by store access (no regression)', function () {
    $owner = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $owner->id]);
    $customer = Customer::factory()->create();
    $account = LoyaltyAccount::create([
        'store_id' => $store->id,
        'customer_id' => $customer->id,
    ]);

    $other = User::factory()->create();
    Store::factory()->create(['user_id' => $other->id]);

    $response = $this->actingAs($other)->postJson(route('stamp.store'), [
        'store_id' => $store->id,
        'token' => $account->public_token,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['token']);
    $account->refresh();
    $this->assertEquals(0, $account->stamp_count);
});

// Preview uses the same 4-char lookup: (store_id, manual_entry_code). Same isolation as stamp/redeem.
// If you need to test preview in your env, POST to /scanner/preview with store_id and token (4-char or full).
