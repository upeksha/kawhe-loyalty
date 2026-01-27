<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$store = \App\Models\Store::first();
if (!$store) {
    echo "No stores found. Create a store first.\n";
    exit(1);
}

echo "=== Store Branding Test ===\n";
echo "Store: {$store->name}\n";
echo "Brand Color: " . ($store->brand_color ?? 'not set') . "\n";
echo "Background Color: " . ($store->background_color ?? 'not set') . "\n";
echo "Pass Logo: " . ($store->pass_logo_path ?? 'not set') . "\n";
echo "Pass Hero: " . ($store->pass_hero_image_path ?? 'not set') . "\n\n";

$account = \App\Models\LoyaltyAccount::where('store_id', $store->id)->first();
if (!$account) {
    echo "No loyalty accounts found for this store.\n";
    exit(1);
}

echo "=== Circle Indicators Test ===\n";
$rewardTarget = $store->reward_target ?? 10;
echo "Reward Target: {$rewardTarget}\n";
echo "Current Stamps: {$account->stamp_count}\n\n";

// Test circle generation
$service = app(\App\Services\Wallet\AppleWalletPassService::class);
$reflection = new \ReflectionClass($service);
$method = $reflection->getMethod('generateCircleIndicators');
$method->setAccessible(true);

echo "Circle Indicators:\n";
for ($i = 0; $i <= min($rewardTarget + 2, 10); $i++) {
    $circles = $method->invoke($service, $i, $rewardTarget);
    echo "  {$i} stamps: {$circles}\n";
}

echo "\n=== Test Complete ===\n";
