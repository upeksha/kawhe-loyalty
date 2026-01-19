<?php

namespace App\Jobs;

use App\Models\LoyaltyAccount;
use App\Services\Wallet\WalletSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UpdateWalletPassJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array<int>
     */
    public $backoff = [10, 30, 60];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public readonly int $loyaltyAccountId
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(WalletSyncService $walletSyncService): void
    {
        try {
            $account = LoyaltyAccount::with(['store', 'customer'])->findOrFail($this->loyaltyAccountId);
            
            Log::info('Updating wallet pass for loyalty account', [
                'loyalty_account_id' => $this->loyaltyAccountId,
                'public_token' => $account->public_token,
            ]);

            $walletSyncService->syncLoyaltyAccount($account);
        } catch (\Exception $e) {
            Log::error('Failed to update wallet pass', [
                'loyalty_account_id' => $this->loyaltyAccountId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('UpdateWalletPassJob failed after all retries', [
            'loyalty_account_id' => $this->loyaltyAccountId,
            'error' => $exception->getMessage(),
        ]);
    }
}
