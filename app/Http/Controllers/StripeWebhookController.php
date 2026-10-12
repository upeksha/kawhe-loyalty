<?php

namespace App\Http\Controllers;

use App\Services\Billing\SubscriptionSyncService;
use Illuminate\Http\Request;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Http\Controllers\WebhookController;
use Laravel\Cashier\Http\Middleware\VerifyWebhookSignature;

class StripeWebhookController extends WebhookController
{
    public function __construct()
    {
        $this->middleware(VerifyWebhookSignature::class);
    }

    public function handleWebhook(Request $request)
    {
        abort_unless(config('cashier.webhook.secret'), 503);
        $live = str_starts_with(config('cashier.secret') ?? '', 'sk_live_') || str_starts_with(config('cashier.secret') ?? '', 'rk_live_');
        abort_unless($request->input('livemode') === $live, 400);

        return parent::handleWebhook($request);
    }

    protected function handleCustomerSubscriptionCreated(array $payload)
    {
        return $this->syncCurrentSubscription($payload);
    }

    protected function handleCustomerSubscriptionUpdated(array $payload)
    {
        return $this->syncCurrentSubscription($payload);
    }

    protected function handleCustomerSubscriptionDeleted(array $payload)
    {
        return $this->syncCurrentSubscription($payload);
    }

    private function syncCurrentSubscription(array $payload)
    {
        if ($user = $this->getUserByStripeId($payload['data']['object']['customer'])) {
            // Fetch current state so retries and out-of-order events cannot restore stale access.
            \Illuminate\Support\Facades\DB::transaction(function () use ($user, $payload) {
                $user = \App\Models\User::whereKey($user->id)->lockForUpdate()->firstOrFail();
                $remote = Cashier::stripe()->subscriptions->retrieve($payload['data']['object']['id']);
                app(SubscriptionSyncService::class)->store($user, $remote);
            });
        }

        return $this->successMethod();
    }
}
