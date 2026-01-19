<?php

namespace App\Services\Wallet;

use App\Models\LoyaltyAccount;
use App\Services\Wallet\Apple\ApplePassService;
use App\Services\Wallet\Apple\ApplePushService;
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
            $serialNumber = $this->applePassService->getSerialNumber($account);
            
            $this->applePushService->sendPassUpdatePushes($passTypeIdentifier, $serialNumber);
        } catch (\Exception $e) {
            Log::error('Failed to send Apple Wallet push notifications', [
                'loyalty_account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);
            // Don't throw - allow Google Wallet sync to continue
        }

        // Google Wallet sync (stub for now)
        // Future: Implement Google Wallet push updates
    }
}
