<?php

use App\Models\Customer;
use App\Models\LoyaltyAccount;
use App\Models\LoyaltyProgram;
use App\Models\Store;
use App\Models\User;
use App\Services\Wallet\Apple\AppleWalletSerial;

test('fromAccount uses loyalty account id for unique serials', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $user->id]);
    $defaultProgram = $store->resolvedDefaultProgram();
    $bonusProgram = LoyaltyProgram::create([
        'store_id' => $store->id,
        'name' => 'Bonus Pastry',
        'reward_target' => 5,
        'reward_title' => 'Free pastry',
        'brand_color' => '#D97706',
        'background_color' => '#431407',
        'registration_form_config' => Store::defaultRegistrationFormConfig(),
        'sort_order' => 2,
        'is_default' => false,
    ]);
    $customer = Customer::factory()->create();

    $accountA = LoyaltyAccount::factory()->create([
        'store_id' => $store->id,
        'customer_id' => $customer->id,
        'loyalty_program_id' => $defaultProgram->id,
    ]);
    $accountB = LoyaltyAccount::factory()->create([
        'store_id' => $store->id,
        'customer_id' => $customer->id,
        'loyalty_program_id' => $bonusProgram->id,
    ]);

    expect(AppleWalletSerial::fromAccount($accountA))->toBe("kawhe-{$accountA->id}");
    expect(AppleWalletSerial::fromAccount($accountB))->toBe("kawhe-{$accountB->id}");
    expect(AppleWalletSerial::fromAccount($accountA))
        ->not->toBe(AppleWalletSerial::fromAccount($accountB));
});

test('resolveAccount resolves current account-id serial format', function () {
    $account = LoyaltyAccount::factory()->create();

    $resolved = AppleWalletSerial::resolveAccount(AppleWalletSerial::fromAccount($account));

    expect($resolved?->id)->toBe($account->id);
});

test('resolveAccount resolves legacy store-customer serial format', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $user->id]);
    $customer = Customer::factory()->create();
    $account = LoyaltyAccount::factory()->create([
        'store_id' => $store->id,
        'customer_id' => $customer->id,
    ]);

    $legacySerial = "kawhe-{$store->id}-{$customer->id}";
    $resolved = AppleWalletSerial::resolveAccount($legacySerial);

    expect($resolved?->id)->toBe($account->id);
});

test('resolveAccount prefers default program for legacy serial with multiple cards', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $user->id]);
    $customer = Customer::factory()->create();
    $defaultProgram = $store->resolvedDefaultProgram();

    $bonusProgram = LoyaltyProgram::create([
        'store_id' => $store->id,
        'name' => 'Bonus Pastry',
        'reward_target' => 5,
        'reward_title' => 'Free pastry',
        'brand_color' => '#D97706',
        'background_color' => '#431407',
        'registration_form_config' => Store::defaultRegistrationFormConfig(),
        'sort_order' => 2,
        'is_default' => false,
    ]);

    $defaultAccount = LoyaltyAccount::factory()->create([
        'store_id' => $store->id,
        'customer_id' => $customer->id,
        'loyalty_program_id' => $defaultProgram->id,
    ]);

    LoyaltyAccount::factory()->create([
        'store_id' => $store->id,
        'customer_id' => $customer->id,
        'loyalty_program_id' => $bonusProgram->id,
    ]);

    $legacySerial = "kawhe-{$store->id}-{$customer->id}";
    $resolved = AppleWalletSerial::resolveAccount($legacySerial);

    expect($resolved?->id)->toBe($defaultAccount->id);
});
