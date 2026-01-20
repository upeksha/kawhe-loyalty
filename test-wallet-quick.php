<?php

// Quick test script for Apple Wallet
// Run: php test-wallet-quick.php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Apple Wallet Quick Test ===\n\n";

// Get or create test account
$user = \App\Models\User::first();
if (!$user) {
    echo "❌ No users found. Create a user first.\n";
    exit(1);
}

echo "✓ Found user: {$user->name} ({$user->email})\n";

$store = $user->stores()->first();
if (!$store) {
    echo "❌ No stores found for user. Create a store first.\n";
    exit(1);
}

echo "✓ Found store: {$store->name} (ID: {$store->id})\n";

$account = \App\Models\LoyaltyAccount::where('store_id', $store->id)->first();
if (!$account) {
    echo "⚠️  No loyalty account found. Creating one...\n";
    
    $customer = \App\Models\Customer::factory()->create([
        'name' => 'Test Customer',
        'email' => 'test@example.com',
    ]);
    
    $account = \App\Models\LoyaltyAccount::factory()->create([
        'store_id' => $store->id,
        'customer_id' => $customer->id,
        'stamp_count' => 5,
        'reward_balance' => 0,
    ]);
    
    echo "✓ Created account: ID {$account->id}\n";
} else {
    echo "✓ Found account: ID {$account->id}\n";
}

echo "\n=== Account Info ===\n";
echo "Account ID: {$account->id}\n";
echo "Public Token: {$account->public_token}\n";
echo "Wallet Auth Token: " . ($account->wallet_auth_token ?? 'NOT SET - run migration!') . "\n";
echo "Serial: kawhe-{$store->id}-{$account->customer_id}\n";
echo "Stamps: {$account->stamp_count}\n";
echo "Rewards: " . ($account->reward_balance ?? 0) . "\n";

// Check if wallet_auth_token exists
if (!$account->wallet_auth_token) {
    echo "\n⚠️  WARNING: wallet_auth_token is missing!\n";
    echo "Run: php artisan migrate\n";
    echo "This will add wallet_auth_token to all accounts.\n";
    exit(1);
}

// Test pass generation
echo "\n=== Testing Pass Generation ===\n";
$service = app(\App\Services\Wallet\AppleWalletPassService::class);
try {
    $pkpass = $service->generatePass($account);
    echo "✓ Pass generated: " . strlen($pkpass) . " bytes\n";
    
    $filename = storage_path('app/test-pass-' . $account->id . '.pkpass');
    file_put_contents($filename, $pkpass);
    echo "✓ Saved to: {$filename}\n";
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

// Test with rewards
echo "\n=== Testing with Rewards ===\n";
$account->reward_balance = 1;
$account->redeem_token = \Illuminate\Support\Str::random(40);
$account->save();

try {
    $pkpass2 = $service->generatePass($account);
    echo "✓ Pass with rewards generated: " . strlen($pkpass2) . " bytes\n";
    
    $filename2 = storage_path('app/test-pass-reward-' . $account->id . '.pkpass');
    file_put_contents($filename2, $pkpass2);
    echo "✓ Saved to: {$filename2}\n";
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n=== Download URLs ===\n";
$downloadUrl = config('app.url') . route('wallet.apple.download', ['public_token' => $account->public_token], false);
echo "Pass download: {$downloadUrl}\n";
echo "Card page: " . config('app.url') . "/card/{$account->public_token}\n";
echo "\n=== QR Codes ===\n";
echo "Stamping QR: LA:{$account->public_token}\n";
if ($account->redeem_token) {
    echo "Redeem QR: LR:{$account->redeem_token}\n";
}

echo "\n✅ Testing complete!\n";
echo "\nNext steps:\n";
echo "1. Visit the download URL to get the pass\n";
echo "2. Add it to Apple Wallet on your iPhone\n";
echo "3. Test scanning the QR code\n";
