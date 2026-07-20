<?php

namespace App\Services\Wallet;

use App\Models\LoyaltyAccount;
use App\Services\Support\SupportAuditService;
use App\Services\Wallet\Apple\ApplePushService;
use App\Services\Wallet\Apple\AppleWalletSerial;
use Illuminate\Support\Facades\Log;

class WalletSyncService
{
    private ?GoogleWalletPassService $googleService = null;

    public function __construct(
        private readonly ApplePushService $applePushService,
        private readonly SupportAuditService $supportAuditService,
        private readonly WalletFailureClassifier $failureClassifier,
    ) {}

    public function syncLoyaltyAccount(LoyaltyAccount $account): void
    {
        $account->loadMissing(['store', 'customer', 'loyaltyProgram']);

        Log::info('Wallet sync requested for loyalty account', [
            'loyalty_account_id' => $account->id,
            'stamp_count' => $account->stamp_count,
            'reward_balance' => $account->reward_balance ?? 0,
            'store_id' => $account->store_id,
        ]);

        $apple = $this->syncApple($account);
        $google = $this->syncGoogle($account);
        $hasFailure = $apple['status'] === 'failed' || $google['status'] === 'failed';
        $retryable = ($apple['retryable'] ?? false) || ($google['retryable'] ?? false);

        $this->supportAuditService->log(
            eventType: 'wallet_sync',
            status: $hasFailure ? 'partial' : 'success',
            storeId: $account->store_id,
            loyaltyAccountId: $account->id,
            source: 'system',
            message: $hasFailure
                ? 'Wallet sync completed with one or more channel issues.'
                : 'Wallet sync completed.',
            metadata: [
                'correlation_id' => (string) str()->uuid(),
                'apple_serial' => AppleWalletSerial::fromAccount($account),
                'apple' => $apple,
                'google' => $google,
                'reward_balance' => $account->reward_balance ?? 0,
                'stamp_count' => $account->stamp_count,
            ]
        );

        if ($retryable) {
            throw new WalletPlatformException(
                'One or more wallet providers are temporarily unavailable.',
                'network',
                true,
            );
        }
    }

    private function syncApple(LoyaltyAccount $account): array
    {
        try {
            $serial = AppleWalletSerial::fromAccount($account);
            $result = $this->applePushService->sendPassUpdatePushes(
                (string) config('passgenerator.pass_type_identifier'),
                $serial
            );

            return [
                'status' => $result['status'] ?? 'success',
                'category' => null,
                'retryable' => false,
                'sent' => $result['sent'] ?? 0,
                'registrations' => $result['registrations'] ?? 0,
            ];
        } catch (\Throwable $exception) {
            $failure = $this->failureClassifier->classify($exception, 'apple');
            Log::error('Wallet sync: Apple Wallet update failed', [
                'loyalty_account_id' => $account->id,
                'category' => $failure->category,
                'retryable' => $failure->retryable,
                'http_status' => $failure->httpStatus,
            ]);

            return $this->failureResult($failure);
        }
    }

    private function syncGoogle(LoyaltyAccount $account): array
    {
        try {
            $this->googleService ??= app(GoogleWalletPassService::class);
            $useGeneric = config('services.google_wallet.pass_type', 'loyalty') === 'generic';

            if ($useGeneric) {
                $this->googleService->createOrUpdateGenericObject($account);
            } else {
                $this->googleService->createOrUpdateLoyaltyObject($account);
            }

            $details = $this->googleSyncDetails();

            return [
                'status' => 'success',
                'category' => null,
                'retryable' => false,
                'class' => $details['class'] ?? ['status' => 'unknown'],
                'object' => $details['object'] ?? ['status' => 'unknown'],
            ];
        } catch (\Throwable $exception) {
            $failure = $this->failureClassifier->classify($exception, 'google');
            Log::error('Wallet sync: Google Wallet update failed', [
                'loyalty_account_id' => $account->id,
                'category' => $failure->category,
                'retryable' => $failure->retryable,
                'http_status' => $failure->httpStatus,
            ]);

            return $this->failureResult($failure);
        }
    }

    private function failureResult(WalletPlatformException $failure): array
    {
        return [
            'status' => 'failed',
            'category' => $failure->category,
            'retryable' => $failure->retryable,
            'http_status' => $failure->httpStatus,
            'message' => $failure->getMessage(),
        ];
    }

    private function googleSyncDetails(): array
    {
        if (! $this->googleService || ! method_exists($this->googleService, 'getLastSyncDetails')) {
            return [];
        }

        try {
            return $this->googleService->getLastSyncDetails();
        } catch (\Throwable $exception) {
            Log::warning('Wallet sync: Google diagnostic details were unavailable', [
                'exception' => $exception::class,
            ]);

            return [];
        }
    }
}
