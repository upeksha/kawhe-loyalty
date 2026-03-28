<?php

use App\Models\Customer;
use App\Models\LoyaltyAccount;
use App\Models\Store;
use Illuminate\Support\Facades\Storage;

test('deleting a loyalty account removes generated stamp strip assets', function () {
    config(['filesystems.assets_disk' => 'public']);
    Storage::fake('public');

    $store = Store::factory()->create();
    $customer = Customer::factory()->create();
    $account = LoyaltyAccount::factory()->create([
        'store_id' => $store->id,
        'customer_id' => $customer->id,
    ]);

    $path = "wallet/google/stamp-strips/store_{$store->id}_account_{$account->id}_test.png";
    Storage::disk('public')->put($path, 'fake');
    Storage::disk('public')->assertExists($path);

    $account->delete();

    Storage::disk('public')->assertMissing($path);
});

test('archiving a store preserves branding assets and generated stamp strips', function () {
    config(['filesystems.assets_disk' => 'public']);
    Storage::fake('public');

    $store = Store::factory()->create([
        'logo_path' => 'logos/test-logo.png',
        'pass_logo_path' => 'pass-logos/test-pass-logo.png',
        'pass_hero_image_path' => 'pass-heroes/test-pass-hero.png',
    ]);

    Storage::disk('public')->put($store->logo_path, 'logo');
    Storage::disk('public')->put($store->pass_logo_path, 'pass-logo');
    Storage::disk('public')->put($store->pass_hero_image_path, 'hero');
    Storage::disk('public')->put("wallet/google/stamp-strips/store_{$store->id}_account_99_a.png", 'strip');

    $store->delete();

    Storage::disk('public')->assertExists($store->logo_path);
    Storage::disk('public')->assertExists($store->pass_logo_path);
    Storage::disk('public')->assertExists($store->pass_hero_image_path);
    Storage::disk('public')->assertExists("wallet/google/stamp-strips/store_{$store->id}_account_99_a.png");
});

test('force deleting a store removes branding assets and generated stamp strips', function () {
    config(['filesystems.assets_disk' => 'public']);
    Storage::fake('public');

    $store = Store::factory()->create([
        'logo_path' => 'logos/test-logo.png',
        'pass_logo_path' => 'pass-logos/test-pass-logo.png',
        'pass_hero_image_path' => 'pass-heroes/test-pass-hero.png',
    ]);

    Storage::disk('public')->put($store->logo_path, 'logo');
    Storage::disk('public')->put($store->pass_logo_path, 'pass-logo');
    Storage::disk('public')->put($store->pass_hero_image_path, 'hero');
    Storage::disk('public')->put("wallet/google/stamp-strips/store_{$store->id}_account_99_a.png", 'strip');

    $store->forceDelete();

    Storage::disk('public')->assertMissing($store->logo_path);
    Storage::disk('public')->assertMissing($store->pass_logo_path);
    Storage::disk('public')->assertMissing($store->pass_hero_image_path);
    Storage::disk('public')->assertMissing("wallet/google/stamp-strips/store_{$store->id}_account_99_a.png");
});
