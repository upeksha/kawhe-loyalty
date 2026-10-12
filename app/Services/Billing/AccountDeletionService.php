<?php

namespace App\Services\Billing;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Laravel\Cashier\Cashier;

class AccountDeletionService
{
    public function delete(User $user): void
    {
        DB::transaction(function () use ($user) {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $this->closeBilling($user);

            // Cashier and Sanctum tables do not cascade on user deletion.
            foreach ($user->subscriptions()->get() as $subscription) {
                $subscription->items()->delete();
                $subscription->delete();
            }
            $user->tokens()->delete();
            if (config('session.driver') === 'database') {
                DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
            }
            $user->delete();
        });
    }

    private function closeBilling(User $user): void
    {
        if (! $user->stripe_id) {
            if ($user->subscriptions()->exists()) {
                $this->blocked('We cannot verify your billing records. Please contact support before deleting your account.');
            }

            return;
        }

        try {
            $stripe = Cashier::stripe();
            $customer = $stripe->customers->retrieve($user->stripe_id);
            // A previous attempt may have closed Stripe before a local database failure.
            if (! ($customer->deleted ?? false)) {
                foreach ($stripe->subscriptions->all(['customer' => $user->stripe_id, 'status' => 'all', 'limit' => 100])->autoPagingIterator() as $subscription) {
                    if (! in_array($subscription->status, ['canceled', 'incomplete_expired'], true)) {
                        $this->blocked('Cancel your subscription in Billing first. Account deletion becomes available once the subscription has ended, including any remaining paid period.');
                    }
                }
                foreach ($stripe->subscriptionSchedules->all(['customer' => $user->stripe_id, 'limit' => 100])->autoPagingIterator() as $schedule) {
                    if (in_array($schedule->status, ['not_started', 'active'], true)) {
                        $this->blocked('A scheduled subscription still exists. Please contact support to resolve it before deleting your account.');
                    }
                }
                foreach (['draft', 'open'] as $status) {
                    if (count($stripe->invoices->all(['customer' => $user->stripe_id, 'status' => $status, 'limit' => 1])->data)) {
                        $this->blocked('There is an unresolved invoice. Please resolve it in Billing or contact support before deleting your account.');
                    }
                }
                if (count($stripe->invoiceItems->all(['customer' => $user->stripe_id, 'pending' => true, 'limit' => 1])->data)) {
                    $this->blocked('There are pending billing items. Please contact support before deleting your account.');
                }
                foreach ($stripe->checkout->sessions->all(['customer' => $user->stripe_id, 'limit' => 100])->autoPagingIterator() as $checkout) {
                    if ($checkout->status === 'open' || ($checkout->status === 'complete' && $checkout->payment_status === 'unpaid')) {
                        $this->blocked('A checkout or payment is still pending. Let it finish or expire, then try again. Contact support if it remains pending.');
                    }
                }

                // Closing the cleared Stripe customer prevents old portal/checkout links
                // from starting new billing after the local identity disappears.
                $deleted = $stripe->customers->delete($user->stripe_id);
                if (! ($deleted->deleted ?? false)) {
                    throw new \RuntimeException('Stripe customer closure was not confirmed');
                }
            }
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::warning('Account deletion blocked because billing could not be confirmed', ['user_id' => $user->id]);
            $this->blocked('We could not confirm that billing is closed. Your account has not been deleted. Please try again later or contact support.');
        }
    }

    private function blocked(string $message): never
    {
        throw ValidationException::withMessages(['billing' => $message])->errorBag('userDeletion');
    }
}
