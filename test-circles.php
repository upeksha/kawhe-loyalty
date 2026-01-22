#!/usr/bin/env php
<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Circle Indicators Test ===\n\n";

$store = \App\Models\Store::first();
if (!$store) {
    echo "❌ No stores found. Create a store first.\n";
    exit(1);
}

echo "Store: {$store->name}\n";
echo "Reward Target: " . ($store->reward_target ?? 10) . "\n\n";

$account = \App\Models\LoyaltyAccount::where('store_id', $store->id)->first();
if (!$account) {
    echo "❌ No loyalty accounts found for this store.\n";
    exit(1);
}

echo "Current Stamps: {$account->stamp_count}\n\n";

$rewardTarget = $store->reward_target ?? 10;

// Test circle generation
$service = app(\App\Services\Wallet\AppleWalletPassService::class);
$reflection = new \ReflectionClass($service);
$method = $reflection->getMethod('generateCircleIndicators');
$method->setAccessible(true);

echo "Circle Indicators (reward_target={$rewardTarget}):\n";
echo str_repeat('-', 50) . "\n";
for ($i = 0; $i <= min($rewardTarget + 2, 15); $i++) {
    $circles = $method->invoke($service, $i, $rewardTarget);
    $status = ($i === $account->stamp_count) ? ' ← current' : '';
    printf("  %2d stamps: %s%s\n", $i, $circles, $status);
}

echo "\n✅ Test Complete\n";
