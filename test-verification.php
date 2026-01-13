<?php

/**
 * Quick Test Helper for Email Verification
 * 
 * Usage: php test-verification.php [email]
 * 
 * This script helps you test email verification locally by:
 * 1. Finding the verification link in logs
 * 2. Or manually verifying an email
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$email = $argv[1] ?? null;

if (!$email) {
    echo "Usage: php test-verification.php [email]\n";
    echo "\n";
    echo "Options:\n";
    echo "  1. Extract verification link from logs\n";
    echo "  2. Manually verify an email\n";
    echo "\n";
    exit(1);
}

// Find customer
$customer = \App\Models\Customer::where('email', $email)->first();

if (!$customer) {
    echo "❌ Customer with email '{$email}' not found.\n";
    exit(1);
}

echo "✅ Found customer: {$customer->name}\n";
echo "   Email: {$customer->email}\n";
echo "   Verified: " . ($customer->email_verified_at ? 'Yes' : 'No') . "\n";
echo "\n";

// Check for verification link in logs
$logFile = storage_path('logs/laravel.log');
if (file_exists($logFile)) {
    $logContent = file_get_contents($logFile);
    
    // Look for verification URLs
    preg_match_all('/http:\/\/[^\s]+verify-email\/[a-zA-Z0-9]{40}[^\s]*/', $logContent, $matches);
    
    if (!empty($matches[0])) {
        $latestLink = end($matches[0]);
        echo "🔗 Latest verification link found in logs:\n";
        echo "   {$latestLink}\n";
        echo "\n";
        echo "💡 Copy and paste this URL in your browser to verify.\n";
    } else {
        echo "ℹ️  No verification links found in logs.\n";
        echo "   Make sure you've clicked 'Verify Email' on the card page.\n";
    }
}

// Option to manually verify
if (!$customer->email_verified_at) {
    echo "\n";
    echo "🔧 To manually verify this email, run:\n";
    echo "   php artisan tinker\n";
    echo "   Then: \$customer = \\App\\Models\\Customer::where('email', '{$email}')->first();\n";
    echo "         \$customer->update(['email_verified_at' => now()]);\n";
    echo "\n";
    
    // Ask if user wants to verify now
    echo "Would you like to verify this email now? (y/n): ";
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    if (trim($line) === 'y' || trim($line) === 'Y') {
        $customer->update(['email_verified_at' => now()]);
        echo "✅ Email verified!\n";
    }
    fclose($handle);
} else {
    echo "✅ Email is already verified.\n";
}

// Show loyalty accounts
$accounts = $customer->loyaltyAccounts()->with('store')->get();
if ($accounts->count() > 0) {
    echo "\n";
    echo "📋 Loyalty Accounts:\n";
    foreach ($accounts as $account) {
        echo "   Store: {$account->store->name}\n";
        echo "   Stamps: {$account->stamp_count} / {$account->store->reward_target}\n";
        echo "   Reward Available: " . ($account->reward_available_at ? 'Yes' : 'No') . "\n";
        echo "   Reward Redeemed: " . ($account->reward_redeemed_at ? 'Yes' : 'No') . "\n";
        echo "   Card URL: http://localhost:8000/c/{$account->public_token}\n";
        echo "\n";
    }
}
