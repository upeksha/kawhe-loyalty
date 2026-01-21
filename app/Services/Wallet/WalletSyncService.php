<?php

namespace App\Services\Wallet;

use App\Models\LoyaltyAccount;
use App\Services\Wallet\Apple\ApplePassService;
use App\Services\Wallet\Apple\ApplePushService;
use App\Services\Wallet\Apple\AppleWalletSerial;
use App\Services\Wallet\GoogleWalletPassService;
use Illuminate\Support\Facades\Log;

/**
 * Service to sync loyalty account state to wallet passes (Apple Wallet & Google Wallet).
 * 
 * Phase 2: Implements Apple Wallet push notifications via APNs.
 */
class WalletSyncService
{
    protected ?ApplePassService $applePassService = null;
    protected ?ApplePushService $applePushService = null;
    protected ?GoogleWalletPassService $googleService = null;

    public function __construct(
        ?ApplePassService $applePassService = null,
        ?ApplePushService $applePushService = null
    ) {
        $this->applePassService = $applePassService ?? app(ApplePassService::class);
        $this->applePushService = $applePushService ?? app(ApplePushService::class);
    }

    /**
     * Sync loyalty account state to wallet passes.
     * 
     * Phase 2: Sends Apple Wallet push notifications via APNs.
     *
     * @param LoyaltyAccount $account
     * @return void
     */
    public function syncLoyaltyAccount(LoyaltyAccount $account): void
    {
        $account->load(['store', 'customer']);

        Log::info('Wallet sync requested for loyalty account', [
            'loyalty_account_id' => $account->id,
            'public_token' => $account->public_token,
            'stamp_count' => $account->stamp_count,
            'reward_balance' => $account->reward_balance ?? 0,
            'store_id' => $account->store_id,
        ]);

        // Phase 2: Send Apple Wallet push notifications
        try {
            $passTypeIdentifier = config('passgenerator.pass_type_identifier');
            // Use centralized serial number helper to ensure consistency
            $serialNumber = AppleWalletSerial::fromAccount($account);
            
            Log::info('Wallet sync: Preparing to send Apple Wallet push notifications', [
                'loyalty_account_id' => $account->id,
                'serial_number' => $serialNumber,
                'pass_type_identifier' => $passTypeIdentifier,
                'stamp_count' => $account->stamp_count,
                'reward_balance' => $account->reward_balance ?? 0,
                'account_updated_at' => $account->updated_at->toIso8601String(),
            ]);
            
            $this->applePushService->sendPassUpdatePushes($passTypeIdentifier, $serialNumber);
            
            Log::info('Wallet sync: Apple Wallet push notifications completed', [
                'loyalty_account_id' => $account->id,
                'serial_number' => $serialNumber,
            ]);
        } catch (\Exception $e) {
            Log::error('Wallet sync: Failed to send Apple Wallet push notifications', [
                'loyalty_account_id' => $account->id,
                'serial_number' => AppleWalletSerial::fromAccount($account),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            // Don't throw - allow Google Wallet sync to continue
        }

        // Google Wallet sync: Update the loyalty object when stamps/rewards change
        try {
            $this->googleService = $this->googleService ?? app(GoogleWalletPassService::class);
            
            Log::info('Wallet sync: Updating Google Wallet loyalty object', [
                'loyalty_account_id' => $account->id,
                'public_token' => $account->public_token,
                'stamp_count' => $account->stamp_count,
                'reward_balance' => $account->reward_balance ?? 0,
            ]);
            
            $this->googleService->createOrUpdateLoyaltyObject($account);
            
            Log::info('Wallet sync: Google Wallet loyalty object updated successfully', [
                'loyalty_account_id' => $account->id,
                'public_token' => $account->public_token,
            ]);
        } catch (\Exception $e) {
            Log::error('Wallet sync: Failed to update Google Wallet loyalty object', [
                'loyalty_account_id' => $account->id,
                'public_token' => $account->public_token,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            // Don't throw - allow the sync to complete even if Google Wallet fails
        }
    }
}
