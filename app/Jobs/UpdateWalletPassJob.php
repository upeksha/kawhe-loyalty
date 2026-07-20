<?php

namespace App\Jobs;

use App\Models\LoyaltyAccount;
use App\Services\Wallet\WalletSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UpdateWalletPassJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 5;

    public int $timeout = 60;

    public int $uniqueFor = 300;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array<int>
     */
    public function backoff(): array
    {
        return [30, 120, 300, 900];
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("wallet-account-sync:{$this->loyaltyAccountId}"))
                ->releaseAfter(20)
                ->expireAfter(120),
        ];
    }

    public function uniqueId(): string
    {
        return (string) $this->loyaltyAccountId;
    }

    /**
     * Create a new job instance.
     */
    public function __construct(
        public readonly int $loyaltyAccountId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(WalletSyncService $walletSyncService): void
    {
        try {
            $account = LoyaltyAccount::with(['store', 'customer', 'loyaltyProgram'])->find($this->loyaltyAccountId);
            if (! $account) {
                Log::notice('Skipping wallet update for missing loyalty account', [
                    'loyalty_account_id' => $this->loyaltyAccountId,
                ]);

                return;
            }

            Log::info('Updating wallet pass for loyalty account', [
                'loyalty_account_id' => $this->loyaltyAccountId,
                'attempt' => $this->attempts(),
            ]);

            $walletSyncService->syncLoyaltyAccount($account);
        } catch (\Exception $e) {
            Log::error('Failed to update wallet pass', [
                'loyalty_account_id' => $this->loyaltyAccountId,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
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

        $account = LoyaltyAccount::find($this->loyaltyAccountId);
        if ($account) {
            app(\App\Services\Support\SupportAuditService::class)->log(
                eventType: 'wallet_sync',
                status: 'failed',
                storeId: $account->store_id,
                loyaltyAccountId: $account->id,
                source: 'queue',
                message: 'Wallet update failed after all retry attempts.',
                metadata: [
                    'attempts' => $this->attempts(),
                    'error_type' => class_basename($exception),
                ]
            );
        }
    }
}
