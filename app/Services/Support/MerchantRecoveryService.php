<?php

namespace App\Services\Support;

use App\Jobs\UpdateWalletPassJob;
use App\Mail\CustomerWelcomeEmail;
use App\Models\LoyaltyAccount;
use App\Models\Store;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class MerchantRecoveryService
{
    public function __construct(
        protected SupportAuditService $supportAuditService
    ) {
    }

    public function resendWelcomeEmail(LoyaltyAccount $account, ?int $actorUserId = null): void
    {
        $account->loadMissing(['customer', 'store']);

        if (! $account->customer?->email) {
            $this->supportAuditService->log(
                eventType: 'welcome_email_send',
                status: 'failed',
                storeId: $account->store_id,
                loyaltyAccountId: $account->id,
                actorUserId: $actorUserId,
                source: 'merchant',
                message: 'Welcome email resend failed because no email is attached to the card.'
            );

            throw new \RuntimeException('No email is attached to this customer.');
        }

        $verificationToken = Str::random(40);

        $account->forceFill([
            'email_verification_token_hash' => hash('sha256', $verificationToken),
            'email_verification_expires_at' => now()->addMinutes(60),
            'email_verification_sent_at' => now(),
        ])->save();

        $mailable = new CustomerWelcomeEmail($account->customer, $account, $verificationToken);

        try {
            if (config('mail.welcome_sync', false)) {
                Mail::to($account->customer->email)->send($mailable);
            } else {
                Mail::to($account->customer->email)->queue($mailable);
            }

            $this->supportAuditService->log(
                eventType: 'welcome_email_send',
                status: 'success',
                storeId: $account->store_id,
                loyaltyAccountId: $account->id,
                actorUserId: $actorUserId,
                source: 'merchant',
                message: 'Merchant resent the customer welcome email.',
                metadata: [
                    'email' => $account->customer->email,
                ]
            );
        } catch (\Throwable $e) {
            $this->supportAuditService->log(
                eventType: 'welcome_email_send',
                status: 'failed',
                storeId: $account->store_id,
                loyaltyAccountId: $account->id,
                actorUserId: $actorUserId,
                source: 'merchant',
                message: 'Welcome email resend failed.',
                metadata: [
                    'email' => $account->customer->email,
                    'error' => $e->getMessage(),
                ]
            );

            throw $e;
        }
    }

    public function queueStoreWalletRefresh(Store $store, ?int $actorUserId = null): int
    {
        $accountIds = LoyaltyAccount::where('store_id', $store->id)->pluck('id');

        foreach ($accountIds as $accountId) {
            UpdateWalletPassJob::dispatch($accountId);
        }

        $this->supportAuditService->log(
            eventType: 'store_wallet_refresh',
            status: 'success',
            storeId: $store->id,
            actorUserId: $actorUserId,
            source: 'merchant',
            message: 'Merchant queued a wallet refresh for all cards in the store.',
            metadata: [
                'queued_accounts' => $accountIds->count(),
            ]
        );

        return $accountIds->count();
    }
}
