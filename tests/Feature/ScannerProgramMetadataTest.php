<?php

use App\Models\Customer;
use App\Models\LoyaltyAccount;
use App\Models\Store;
use App\Models\User;

test('scanner preview returns loyalty program metadata for the scanned card', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create([
        'user_id' => $user->id,
        'reward_title' => 'Free coffee',
        'reward_target' => 8,
        'require_verification_for_redemption' => true,
    ]);
    $program = $store->resolvedDefaultProgram();
    $program->update([
        'name' => 'Morning Coffee Card',
        'reward_title' => 'Free long black',
        'reward_target' => 9,
    ]);

    $customer = Customer::factory()->create(['name' => 'Casey']);
    $account = LoyaltyAccount::factory()->create([
        'store_id' => $store->id,
        'loyalty_program_id' => $program->id,
        'customer_id' => $customer->id,
        'reward_balance' => 1,
    ]);

    $this->actingAs($user)
        ->withSession(['_token' => 'test-token'])
        ->withHeader('X-CSRF-TOKEN', 'test-token')
        ->postJson('/scanner/preview', [
            'store_id' => $store->id,
            'token' => $account->public_token,
        ])
        ->assertOk()
        ->assertJson([
            'success' => true,
            'store_id' => $store->id,
            'store_name' => $store->name,
            'program_id' => $program->id,
            'program_name' => 'Morning Coffee Card',
            'reward_title' => 'Free long black',
            'reward_target' => 9,
            'requires_verification' => true,
        ]);
});

test('redeem info returns loyalty program metadata for the scanned card', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create([
        'user_id' => $user->id,
        'reward_title' => 'Free coffee',
        'reward_target' => 8,
        'require_verification_for_redemption' => false,
    ]);
    $program = $store->resolvedDefaultProgram();
    $program->update([
        'name' => 'Lunch Club',
        'reward_title' => 'Free toastie',
        'reward_target' => 7,
    ]);

    $customer = Customer::factory()->create([
        'name' => 'Morgan',
        'email' => 'morgan@example.com',
    ]);
    $account = LoyaltyAccount::factory()->create([
        'store_id' => $store->id,
        'loyalty_program_id' => $program->id,
        'customer_id' => $customer->id,
        'stamp_count' => 6,
        'reward_balance' => 2,
        'redeem_token' => 'redeemtokenprogrammeta',
    ]);

    $this->actingAs($user)
        ->withSession(['_token' => 'test-token'])
        ->withHeader('X-CSRF-TOKEN', 'test-token')
        ->postJson('/redeem/info', [
            'store_id' => $store->id,
            'token' => $account->redeem_token,
        ])
        ->assertOk()
        ->assertJson([
            'success' => true,
            'store_id' => $store->id,
            'store_name' => $store->name,
            'program_id' => $program->id,
            'program_name' => 'Lunch Club',
            'reward_title' => 'Free toastie',
            'reward_target' => 7,
            'current_stamps' => 6,
            'available_rewards' => 2,
            'requires_verification' => false,
        ]);
});
