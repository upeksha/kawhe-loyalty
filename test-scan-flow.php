<?php

/**
 * Test script to diagnose scanner server errors
 * 
 * Usage: php test-scan-flow.php [public_token] [store_id]
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\LoyaltyAccount;
use App\Models\User;
use App\Services\Loyalty\StampLoyaltyService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

echo "=== Scanner Flow Diagnostic Test ===\n\n";

// Get account
$publicToken = $argv[1] ?? null;
if (!$publicToken) {
    $account = LoyaltyAccount::with(['store', 'customer'])->latest()->first();
    if (!$account) {
        die("No loyalty accounts found. Please create one first.\n");
    }
    $publicToken = $account->public_token;
    echo "Using latest account: {$publicToken}\n";
} else {
    $account = LoyaltyAccount::with(['store', 'customer'])->where('public_token', $publicToken)->first();
    if (!$account) {
        die("Account not found for token: {$publicToken}\n");
    }
}

$storeId = $argv[2] ?? $account->store_id;
$user = User::whereHas('stores', function($q) use ($storeId) {
    $q->where('stores.id', $storeId);
})->first();

if (!$user) {
    die("No user found for store ID: {$storeId}\n");
}

echo "Account: {$account->id}\n";
echo "Store: {$account->store->name} (ID: {$account->store_id})\n";
echo "Customer: " . ($account->customer->name ?? $account->customer->email ?? 'N/A') . "\n";
echo "User: {$user->name} (ID: {$user->id})\n";
echo "Current Stamp Count: {$account->stamp_count}\n";
echo "Current Reward Balance: " . ($account->reward_balance ?? 0) . "\n\n";

// Test 1: Check database connection
echo "Test 1: Database Connection\n";
try {
    DB::connection()->getPdo();
    echo "✓ Database connection OK\n\n";
} catch (\Exception $e) {
    echo "✗ Database connection failed: " . $e->getMessage() . "\n\n";
    exit(1);
}

// Test 2: Check queue connection
echo "Test 2: Queue Connection\n";
try {
    $queueConnection = config('queue.default');
    echo "Queue driver: {$queueConnection}\n";
    
    if ($queueConnection === 'database') {
        $queueTable = config('queue.connections.database.table', 'jobs');
        $tableExists = DB::getSchemaBuilder()->hasTable($queueTable);
        echo "Queue table exists: " . ($tableExists ? "Yes" : "No") . "\n";
    }
    echo "✓ Queue configuration OK\n\n";
} catch (\Exception $e) {
    echo "✗ Queue configuration error: " . $e->getMessage() . "\n\n";
}

// Test 3: Check broadcasting
echo "Test 3: Broadcasting Configuration\n";
try {
    $broadcastDriver = config('broadcasting.default');
    echo "Broadcast driver: {$broadcastDriver}\n";
    
    if ($broadcastDriver === 'reverb') {
        $reverbHost = config('reverb.host');
        $reverbPort = config('reverb.port');
        echo "Reverb host: {$reverbHost}:{$reverbPort}\n";
    }
    echo "✓ Broadcasting configuration OK\n\n";
} catch (\Exception $e) {
    echo "✗ Broadcasting configuration error: " . $e->getMessage() . "\n\n";
}

// Test 4: Test stamp service directly
echo "Test 4: Direct Stamp Service Test\n";
try {
    $stampService = app(StampLoyaltyService::class);
    
    echo "Attempting to stamp...\n";
    $result = $stampService->stamp(
        account: $account,
        staff: $user,
        count: 1,
        idempotencyKey: 'test-' . time() . '-' . rand(1000, 9999),
        userAgent: 'test-script',
        ipAddress: '127.0.0.1'
    );
    
    echo "✓ Stamp successful!\n";
    echo "  New stamp count: {$result->stampCount}\n";
    echo "  New reward balance: {$result->rewardBalance}\n";
    echo "  Reward earned: " . ($result->rewardEarned ? "Yes" : "No") . "\n";
    echo "  Is duplicate: " . ($result->isDuplicate ? "Yes" : "No") . "\n\n";
} catch (\Exception $e) {
    echo "✗ Stamp failed: " . $e->getMessage() . "\n";
    echo "  Class: " . get_class($e) . "\n";
    echo "  File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "  Trace:\n" . $e->getTraceAsString() . "\n\n";
    exit(1);
}

// Test 5: Check if events are being dispatched
echo "Test 5: Event Dispatch Test\n";
try {
    $account->refresh();
    \App\Events\StampUpdated::dispatch($account);
    echo "✓ Event dispatched successfully\n\n";
} catch (\Exception $e) {
    echo "✗ Event dispatch failed: " . $e->getMessage() . "\n";
    echo "  This might cause server errors but won't break the scan\n\n";
}

// Test 6: Check if jobs can be dispatched
echo "Test 6: Job Dispatch Test\n";
try {
    \App\Jobs\UpdateWalletPassJob::dispatch($account->id)->onQueue('default');
    echo "✓ Job dispatched successfully\n\n";
} catch (\Exception $e) {
    echo "✗ Job dispatch failed: " . $e->getMessage() . "\n";
    echo "  This might cause server errors but won't break the scan\n\n";
}

// Test 7: Simulate full scan request
echo "Test 7: Simulating Full Scan Request\n";
try {
    // Simulate what the controller does
    $account->refresh();
    $account->load(['store', 'customer']);
    
    // Try to get transaction
    $idempotencyKey = 'test-' . time();
    $transaction = null;
    if (\Schema::hasTable('points_transactions')) {
        $transaction = \App\Models\PointsTransaction::where('idempotency_key', $idempotencyKey)->first();
    }
    
    $response = [
        'status' => 'success',
        'success' => true,
        'message' => 'Successfully added 1 stamp!',
        'storeName' => $account->store->name,
        'customerLabel' => $account->customer->name ?? 'Customer',
        'stampCount' => $account->stamp_count,
        'rewardBalance' => $account->reward_balance ?? 0,
        'receipt' => [
            'transaction_id' => $transaction->id ?? null,
            'timestamp' => now()->toIso8601String(),
        ],
    ];
    
    echo "✓ Response would be:\n";
    echo json_encode($response, JSON_PRETTY_PRINT) . "\n\n";
} catch (\Exception $e) {
    echo "✗ Response generation failed: " . $e->getMessage() . "\n\n";
}

// Test 8: Check for common issues
echo "Test 8: Common Issues Check\n";
$issues = [];

// Check memory limit
$memoryLimit = ini_get('memory_limit');
echo "Memory limit: {$memoryLimit}\n";

// Check execution time
$maxExecutionTime = ini_get('max_execution_time');
echo "Max execution time: {$maxExecutionTime}\n";

// Check if queue worker is running (for database queue)
if (config('queue.default') === 'database') {
    $pendingJobs = DB::table('jobs')->count();
    echo "Pending jobs in queue: {$pendingJobs}\n";
    if ($pendingJobs > 100) {
        $issues[] = "High number of pending jobs ({$pendingJobs}) - queue worker may not be running";
    }
}

// Check recent errors in logs
$logFile = storage_path('logs/laravel.log');
if (file_exists($logFile)) {
    $recentErrors = shell_exec("tail -n 50 {$logFile} | grep -i 'error\|exception\|fatal' | tail -n 5");
    if ($recentErrors) {
        echo "\nRecent errors in logs:\n";
        echo $recentErrors . "\n";
    }
}

if (empty($issues)) {
    echo "✓ No common issues detected\n\n";
} else {
    echo "\n⚠ Issues detected:\n";
    foreach ($issues as $issue) {
        echo "  - {$issue}\n";
    }
    echo "\n";
}

echo "=== Test Complete ===\n";
echo "\nIf scans are still failing intermittently, check:\n";
echo "1. Queue worker is running: php artisan queue:work\n";
echo "2. Reverb server is running (if using broadcasting)\n";
echo "3. Database connection is stable\n";
echo "4. Memory and execution time limits are sufficient\n";
echo "5. Recent errors in logs: tail -f storage/logs/laravel.log\n";
