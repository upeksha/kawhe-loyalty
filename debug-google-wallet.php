#!/usr/bin/env php
<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Google Wallet Debug ===\n\n";

// 1. Check configuration
echo "1. Checking configuration...\n";
$issuerId = config('services.google_wallet.issuer_id');
$classId = config('services.google_wallet.class_id');
$serviceAccountKey = config('services.google_wallet.service_account_key');

echo "   Issuer ID: " . ($issuerId ?: '❌ NOT SET') . "\n";
echo "   Class ID: " . ($classId ?: '❌ NOT SET') . "\n";
echo "   Service Account Key Path: " . ($serviceAccountKey ?: '❌ NOT SET') . "\n\n";

// 2. Check service account file
echo "2. Checking service account file...\n";
if (!$serviceAccountKey) {
    echo "   ❌ Service account key path not configured\n";
    exit(1);
}

$paths = [
    $serviceAccountKey,
    storage_path('app/private/' . $serviceAccountKey),
    base_path($serviceAccountKey),
];

$foundPath = null;
foreach ($paths as $path) {
    if (file_exists($path)) {
        $foundPath = $path;
        echo "   ✅ Found at: {$path}\n";
        break;
    }
}

if (!$foundPath) {
    echo "   ❌ Service account key not found in any of these locations:\n";
    foreach ($paths as $path) {
        echo "      - {$path}\n";
    }
    exit(1);
}

if (!is_readable($foundPath)) {
    echo "   ❌ File exists but is not readable. Check permissions.\n";
    exit(1);
}

// 3. Check JSON validity
echo "\n3. Checking service account JSON...\n";
$credentials = json_decode(file_get_contents($foundPath), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo "   ❌ Invalid JSON: " . json_last_error_msg() . "\n";
    exit(1);
}

if (!isset($credentials['client_email'])) {
    echo "   ❌ Missing 'client_email' in credentials\n";
    exit(1);
}

if (!isset($credentials['private_key'])) {
    echo "   ❌ Missing 'private_key' in credentials\n";
    exit(1);
}

echo "   ✅ Valid JSON\n";
echo "   Service Account Email: {$credentials['client_email']}\n\n";

// 4. Check Google API Client
echo "4. Checking Google API Client...\n";
try {
    if (!class_exists('Google_Client')) {
        echo "   ❌ Google API Client not installed. Run: composer require google/apiclient\n";
        exit(1);
    }
    echo "   ✅ Google API Client installed\n\n";
} catch (\Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

// 5. Try to initialize service
echo "5. Testing service initialization...\n";
try {
    $service = app(\App\Services\Wallet\GoogleWalletPassService::class);
    echo "   ✅ Service initialized successfully\n\n";
} catch (\Exception $e) {
    echo "   ❌ Failed to initialize service:\n";
    echo "   Error: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "   Trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

// 6. Test with a real account
echo "6. Testing with real account...\n";
try {
    $account = \App\Models\LoyaltyAccount::with(['store', 'customer'])->first();
    if (!$account) {
        echo "   ⚠️  No loyalty accounts found. Skipping account test.\n";
        exit(0);
    }
    
    echo "   Account ID: {$account->id}\n";
    echo "   Store: {$account->store->name}\n";
    echo "   Stamps: {$account->stamp_count}\n\n";
    
    echo "   Testing createOrUpdateLoyaltyObject...\n";
    $loyaltyObject = $service->createOrUpdateLoyaltyObject($account);
    echo "   ✅ Loyalty object created/updated successfully\n";
    echo "   Object ID: " . $loyaltyObject->getId() . "\n\n";
    
    echo "   Testing generateSaveLink...\n";
    $saveUrl = $service->generateSaveLink($account);
    echo "   ✅ Save link generated successfully\n";
    echo "   URL length: " . strlen($saveUrl) . " characters\n";
    echo "   URL preview: " . substr($saveUrl, 0, 80) . "...\n\n";
    
} catch (\Exception $e) {
    echo "   ❌ Error during account test:\n";
    echo "   Error: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "   Trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

echo "✅ All checks passed!\n";
