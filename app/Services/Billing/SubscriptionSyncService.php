<?php

namespace App\Services\Billing;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Cashier\Cashier;

class SubscriptionSyncService
{
    public function sync(User $user): void
    {
        if (! $user->stripe_id) {
            return;
        }
        DB::transaction(function () use ($user) {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            foreach (Cashier::stripe()->subscriptions->all(['customer' => $user->stripe_id, 'status' => 'all', 'limit' => 100])->autoPagingIterator() as $subscription) {
                $this->store($user, $subscription);
            }
        });
        $user->unsetRelation('subscriptions');
    }

    public function store(User $user, $remote)
    {
        abort_unless($user->stripe_id && $remote->customer === $user->stripe_id, 403);

        return DB::transaction(function () use ($user, $remote) {
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->stripe_id === $remote->customer, 403);
            $items = $remote->items->data;
            $periodEnd = $remote->current_period_end ?? ($items[0]->current_period_end ?? null);
            $end = $remote->cancel_at ?? (($remote->cancel_at_period_end ?? false) ? $periodEnd : null);
            if (in_array($remote->status, ['canceled', 'incomplete_expired'], true)) {
                $end = $remote->ended_at ?? time();
            }
            $subscription = $locked->subscriptions()->updateOrCreate(['stripe_id' => $remote->id], [
                'type' => 'default', 'stripe_status' => $remote->status,
                'stripe_price' => count($items) === 1 ? $items[0]->price->id : null,
                'quantity' => count($items) === 1 ? ($items[0]->quantity ?? 1) : null,
                'trial_ends_at' => $remote->trial_end ? Carbon::createFromTimestamp($remote->trial_end) : null,
                'ends_at' => $end ? Carbon::createFromTimestamp($end) : null,
                'created_at' => Carbon::createFromTimestamp($remote->created),
            ]);
            $ids = [];
            foreach ($items as $item) {
                $ids[] = $item->id;
                $subscription->items()->updateOrCreate(['stripe_id' => $item->id], [
                    'stripe_product' => $item->price->product, 'stripe_price' => $item->price->id, 'quantity' => $item->quantity ?? 1,
                ]);
            }
            $subscription->items()->whereNotIn('stripe_id', $ids)->delete();
            $user->unsetRelation('subscriptions');

            return $subscription;
        });
    }

    public function checkoutSession(User $user, string $id)
    {
        $session = Cashier::stripe()->checkout->sessions->retrieve($id);
        // Never infer ownership from an email or bind a customer supplied by a return URL.
        abort_unless($user->stripe_id && $session->customer === $user->stripe_id, 403);
        abort_if($session->client_reference_id && (string) $session->client_reference_id !== (string) $user->id, 403);
        abort_unless($session->mode === 'subscription', 422);
        if ($session->status === 'complete' && in_array($session->payment_status, ['paid', 'no_payment_required'], true) && $session->subscription) {
            $this->store($user, Cashier::stripe()->subscriptions->retrieve($session->subscription));
        }

        return $session;
    }
}
