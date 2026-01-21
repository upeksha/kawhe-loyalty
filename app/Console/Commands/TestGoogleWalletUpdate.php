<?php

namespace App\Console\Commands;

use App\Models\LoyaltyAccount;
use App\Services\Wallet\GoogleWalletPassService;
use App\Services\Wallet\WalletSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestGoogleWalletUpdate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wallet:test-google-update {public_token?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test Google Wallet pass update for a loyalty account';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $publicToken = $this->argument('public_token');
        
        if (!$publicToken) {
            // Get latest account
            $account = LoyaltyAccount::with(['store', 'customer'])->latest()->first();
            if (!$account) {
                $this->error('No loyalty accounts found. Please create one first.');
                return 1;
            }
            $publicToken = $account->public_token;
            $this->info("Using latest account: {$publicToken}");
        } else {
            $account = LoyaltyAccount::with(['store', 'customer'])->where('public_token', $publicToken)->first();
            if (!$account) {
                $this->error("Loyalty account not found for token: {$publicToken}");
                return 1;
            }
        }

        $this->info("Testing Google Wallet update for account:");
        $this->line("  ID: {$account->id}");
        $this->line("  Store: {$account->store->name} (ID: {$account->store_id})");
        $this->line("  Customer: " . ($account->customer->name ?? $account->customer->email ?? 'N/A'));
        $this->line("  Stamp Count: {$account->stamp_count}");
        $this->line("  Reward Balance: " . ($account->reward_balance ?? 0));
        $this->line("  Public Token: {$account->public_token}");
        $this->newLine();

        // Test direct Google Wallet service update
        $this->info("Step 1: Testing direct Google Wallet service update...");
        try {
            $googleService = app(GoogleWalletPassService::class);
            $objectId = $this->getObjectId($account);
            $this->line("  Object ID: {$objectId}");
            
            // Try to get existing object first
            try {
                $existing = $googleService->getService()->loyaltyobject->get($objectId);
                $this->info("  ✓ Object exists in Google Wallet");
                $this->line("  Current stamp count in Wallet: " . ($existing->getLoyaltyPoints()->getBalance()->getInt() ?? 'N/A'));
            } catch (\Exception $e) {
                $this->warn("  ⚠ Object not found in Google Wallet (will be created)");
                $this->line("  Error: " . $e->getMessage());
            }
            
            // Update the object
            $result = $googleService->createOrUpdateLoyaltyObject($account);
            $this->info("  ✓ Google Wallet object updated successfully");
            $this->line("  Updated stamp count: " . ($result->getLoyaltyPoints()->getBalance()->getInt() ?? 'N/A'));
            
        } catch (\Exception $e) {
            $this->error("  ✗ Failed to update Google Wallet object");
            $this->line("  Error: " . $e->getMessage());
            $this->line("  Code: " . $e->getCode());
            if ($e->getCode() === 403) {
                $this->warn("  This might be a permissions issue. Check:");
                $this->line("    - Service account has WRITER role in Google Wallet Console");
                $this->line("    - Service account key file is correct");
            }
            return 1;
        }

        $this->newLine();
        
        // Test via WalletSyncService
        $this->info("Step 2: Testing via WalletSyncService (full sync)...");
        try {
            $walletSyncService = app(WalletSyncService::class);
            $walletSyncService->syncLoyaltyAccount($account);
            $this->info("  ✓ Wallet sync completed successfully");
        } catch (\Exception $e) {
            $this->error("  ✗ Wallet sync failed");
            $this->line("  Error: " . $e->getMessage());
            return 1;
        }

        $this->newLine();
        $this->info("✓ Test completed successfully!");
        $this->line("");
        $this->line("Next steps:");
        $this->line("  1. Open Google Wallet app on your device");
        $this->line("  2. Find the loyalty card");
        $this->line("  3. Pull down to refresh (or wait a few minutes for auto-sync)");
        $this->line("  4. Check if stamp count and rewards have updated");
        $this->line("");
        $this->line("Note: Google Wallet may take a few minutes to sync updates.");
        $this->line("      If updates don't appear, try removing and re-adding the pass.");

        return 0;
    }

    /**
     * Get object ID for account (matching GoogleWalletPassService logic)
     */
    protected function getObjectId(LoyaltyAccount $account): string
    {
        $issuerId = config('services.google_wallet.issuer_id');
        return sprintf('%s.%s', $issuerId, sprintf('loyalty_object_%d', $account->id));
    }
}
