<?php

namespace App\Http\Controllers;

use App\Services\Billing\UsageService;
use App\Services\Support\SupportAuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Stripe;

class BillingController extends Controller
{
    protected $usageService;

    public function __construct(UsageService $usageService, protected SupportAuditService $supportAuditService)
    {
        $this->usageService = $usageService;
    }

    /**
     * Show billing overview page.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Refresh subscription from Stripe if needed
        if ($request->has('refresh')) {
            try {
                $this->refreshStripeCustomerState($user);
            } catch (\Exception $e) {
                Log::warning('Failed to sync subscription', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $stats = $this->usageService->getUsageStats($user);
        $subscription = $user->subscription('default');
        $planState = $this->buildPlanState($stats, $subscription);

        return view('billing.index', [
            'stats' => $stats,
            'subscription' => $subscription,
            'stripePriceId' => config('cashier.price_id'),
            'planState' => $planState,
        ]);
    }

    /**
     * Create Stripe Checkout session for subscription.
     */
    public function checkout(Request $request)
    {
        $user = $request->user();

        // Check if Stripe is configured
        $stripeKey = config('cashier.key');
        $stripeSecret = config('cashier.secret');
        $priceId = config('cashier.price_id');

        if (! $stripeKey || ! $stripeSecret) {
            Log::error('Stripe keys not configured', [
                'user_id' => $user->id,
                'has_key' => ! empty($stripeKey),
                'has_secret' => ! empty($stripeSecret),
            ]);

            $this->supportAuditService->log(
                eventType: 'billing_issue',
                status: 'failed',
                actorUserId: $user->id,
                source: 'billing.checkout',
                message: 'Checkout blocked because Stripe keys are not configured.'
            );

            return back()->withErrors([
                'error' => 'Checkout is not ready yet for this account. Please try syncing billing first, and contact support if the problem continues.',
            ]);
        }

        if (! $priceId) {
            Log::error('Stripe price ID not configured', [
                'user_id' => $user->id,
            ]);

            $this->supportAuditService->log(
                eventType: 'billing_issue',
                status: 'failed',
                actorUserId: $user->id,
                source: 'billing.checkout',
                message: 'Checkout blocked because Stripe price ID is not configured.'
            );

            return back()->withErrors([
                'error' => 'The Pro plan checkout is not fully configured yet. Please contact support before trying again.',
            ]);
        }

        try {
            $appUrl = config('app.url');
            $checkout = $this->createCheckoutSession($user, $priceId, $appUrl);

            return redirect($checkout->url);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            if ($this->isMissingStripeCustomer($e) && ! empty($user->stripe_id)) {
                Log::warning('Retrying Stripe checkout after clearing stale Stripe customer ID', [
                    'user_id' => $user->id,
                    'old_stripe_id' => $user->stripe_id,
                ]);

                $this->resetStripeCustomer($user);

                try {
                    $checkout = $this->createCheckoutSession($user->fresh(), $priceId, config('app.url'));

                    return redirect($checkout->url);
                } catch (\Exception $retryException) {
                    $e = $retryException;
                }
            }

            Log::error('Stripe checkout failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->supportAuditService->log(
                eventType: 'billing_issue',
                status: 'failed',
                actorUserId: $user->id,
                source: 'billing.checkout',
                message: 'Stripe checkout failed.',
                metadata: ['error' => $e->getMessage()]
            );

            return back()->withErrors([
                'error' => 'We could not start checkout right now. Please try again in a moment.',
            ]);
        }
    }

    /**
     * Redirect to Stripe Billing Portal.
     */
    public function portal(Request $request)
    {
        $user = $request->user();

        try {
            $portalUrl = $user->billingPortalUrl(route('billing.index'));

            return redirect($portalUrl);
        } catch (\Exception $e) {
            if ($this->isMissingStripeCustomer($e) && ! empty($user->stripe_id)) {
                Log::warning('Billing portal failed because stored Stripe customer is missing; clearing stale Stripe customer ID', [
                    'user_id' => $user->id,
                    'old_stripe_id' => $user->stripe_id,
                ]);

                $this->resetStripeCustomer($user);

                return back()->withErrors([
                    'error' => 'Your billing link was out of date after the Stripe account change. Please try again once more and a fresh billing profile will be created automatically.',
                ]);
            }

            Log::error('Stripe billing portal failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['error' => 'We could not open the billing portal right now. Please try again shortly.']);
        }
    }

    /**
     * Show success page after subscription.
     * Handles checkout session retrieval and subscription sync.
     */
    public function success(Request $request)
    {
        $sessionId = $request->query('session_id');
        if (! $sessionId) {
            return view('billing.success', ['error' => 'No session ID provided. Please check your subscription status on the billing page.', 'hasSession' => false, 'nextSteps' => $this->billingSuccessNextSteps('error')]);
        }
        try {
            $session = app(\App\Services\Billing\SubscriptionSyncService::class)->checkoutSession($request->user(), $sessionId);
            if ($session->status === 'complete' && in_array($session->payment_status, ['paid', 'no_payment_required'], true) && $this->usageService->isSubscribed($request->user())) {
                return redirect()->route('dashboard')->with('success', 'Your Pro subscription is active.');
            }

            return view('billing.success', ['message' => 'Payment has not completed. No paid plan has been activated by this checkout. Check billing or return to checkout.', 'hasSession' => true, 'canRetry' => true, 'sessionId' => $sessionId, 'nextSteps' => $this->billingSuccessNextSteps('processing')]);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::warning('Checkout status unavailable', ['user_id' => $request->user()->id]);

            return view('billing.success', ['error' => 'We could not confirm your payment yet. Refresh billing before starting another checkout.', 'hasSession' => true, 'canRetry' => true, 'sessionId' => $sessionId, 'nextSteps' => $this->billingSuccessNextSteps('error')]);
        }
    }

    public function sync(Request $request)
    {
        $request->validate(['session_id' => ['nullable', 'string', 'max:255']]);
        try {
            $sync = app(\App\Services\Billing\SubscriptionSyncService::class);
            if ($request->filled('session_id')) {
                $sync->checkoutSession($request->user(), $request->input('session_id'));
            } else {
                $sync->sync($request->user());
            }

            return $request->expectsJson()
                ? response()->json(['success' => true, 'subscription_status' => $request->user()->subscription('default')?->stripe_status])
                : redirect()->route('billing.index')->with('success', 'Subscription status has been synced.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::warning('Billing sync unavailable', ['user_id' => $request->user()->id]);

            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => 'Billing is temporarily unavailable. Please try again shortly.'], 503)
                : back()->withErrors(['error' => 'Billing is temporarily unavailable. Please try again shortly.']);
        }
    }

    /**
     * Show cancel page after cancelled checkout.
     */
    public function cancel(Request $request)
    {
        $user = $request->user();
        $stats = $this->usageService->getUsageStats($user);

        return view('billing.cancel', [
            'stats' => $stats,
            'nextSteps' => [
                'Nothing has been charged. Your current plan stays exactly as it was.',
                ($stats['can_create_program'] ?? false)
                    ? 'You still have room to add another loyalty card on your current plan if you want to wait before upgrading.'
                    : 'If you need another loyalty card later, you can return to billing anytime and restart checkout when you are ready.',
                'You can review usage and plan limits on the billing page before deciding again.',
            ],
        ]);
    }

    protected function buildPlanState(array $stats, $subscription): array
    {
        $proStores = config('billing.plans.pro.stores');
        $proCards = config('billing.plans.pro.programs_per_store');

        if ($subscription && in_array($subscription->stripe_status, ['past_due', 'unpaid', 'incomplete', 'paused'], true)) {
            return ['label' => 'Payment needs attention', 'tone' => 'bg-red-100 text-red-700',
                'summary' => 'Your subscription payment needs attention. Paid limits are paused until billing is resolved.',
                'transition' => 'Open Manage Subscription to update your payment method or pay an outstanding invoice. Existing customer cards keep working.'];
        }

        if ($subscription && ($stats['is_subscribed'] ?? false) && ! $subscription->ends_at) {
            return [
                'label' => 'Pro active',
                'tone' => 'bg-emerald-100 text-emerald-700',
                'summary' => "Your Pro plan is active: up to {$proStores} stores, {$proCards} loyalty cards per store, and unlimited customers per card.",
                'transition' => 'If you cancel later, your subscription stays active until the end of the billing period.',
            ];
        }

        if ($subscription && ($stats['is_subscribed'] ?? false) && $subscription->ends_at && $subscription->ends_at->isFuture()) {
            return [
                'label' => 'Pro ending',
                'tone' => 'bg-amber-100 text-amber-700',
                'summary' => 'Your Pro plan is still active for now, but it is scheduled to end on '.$subscription->ends_at->format('M d, Y').'.',
                'transition' => 'After the billing period ends, limits fall back to Free (1 store, 1 card, 100 customers per card). Existing stores, cards, and customers keep working — only new growth is gated.',
            ];
        }

        if (! ($stats['is_subscribed'] ?? false) && ! ($stats['can_accept_new_customer'] ?? true)) {
            return [
                'label' => 'Free plan full',
                'tone' => 'bg-accent-100 text-accent-700',
                'summary' => 'Your free plan has reached its 100-customer limit on your primary loyalty card.',
                'transition' => "Pro expands to {$proStores} stores, {$proCards} cards per store, and unlimited customers. Existing customers keep scanning and redeeming.",
            ];
        }

        return [
            'label' => 'Free plan active',
            'tone' => 'bg-stone-100 text-stone-700',
            'summary' => 'Free includes 1 store, 1 loyalty card, and up to 100 customers on that card.',
            'transition' => 'Upgrade to Pro when you need more stores, cards, or customer capacity.',
        ];
    }

    protected function billingSuccessNextSteps(string $mode): array
    {
        return match ($mode) {
            'processing' => [
                'Return to billing and use Refresh Status if the plan does not update automatically within a few minutes.',
                'Existing customers can keep using their cards while payment finishes processing.',
            ],
            'error' => [
                'Go back to billing and refresh status before retrying checkout.',
                'If the problem persists, contact support with your checkout details.',
            ],
            default => [
                'Return to billing to review your current plan state.',
            ],
        };
    }

    protected function createCheckoutSession($user, string $priceId, string $appUrl)
    {
        if ($user->stripe_id) {
            $stripe = \Laravel\Cashier\Cashier::stripe();
            foreach ($stripe->subscriptions->all(['customer' => $user->stripe_id, 'status' => 'all', 'limit' => 100])->autoPagingIterator() as $subscription) {
                if (! in_array($subscription->status, ['canceled', 'incomplete_expired'], true)) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['error' => 'You already have a subscription or an unresolved payment. Use Manage Subscription to update, resume, or resolve it.']);
                }
            }
            foreach ($stripe->checkout->sessions->all(['customer' => $user->stripe_id, 'limit' => 100])->autoPagingIterator() as $session) {
                if ($session->status === 'complete' && $session->payment_status === 'unpaid') {
                    throw \Illuminate\Validation\ValidationException::withMessages(['error' => 'Your payment is still processing. Refresh billing before trying another checkout.']);
                }
                if ($session->status === 'open' && $session->mode === 'subscription' && (string) $session->client_reference_id === (string) $user->id) {
                    return $session;
                }
            }
        }

        return $user->newSubscription('default', $priceId)
            ->allowPromotionCodes()
            ->checkout([
                'success_url' => $appUrl.'/billing/success?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('billing.cancel'),
                'client_reference_id' => (string) $user->id,
            ]);
    }

    protected function refreshStripeCustomerState($user): void
    {
        app(\App\Services\Billing\SubscriptionSyncService::class)->sync($user);
    }

    protected function resetStripeCustomer($user): void
    {
        $user->forceFill([
            'stripe_id' => null,
            'pm_type' => null,
            'pm_last_four' => null,
            'trial_ends_at' => null,
        ])->save();
    }

    protected function isMissingStripeCustomer(\Throwable $e): bool
    {
        return str_contains($e->getMessage(), 'No such customer:');
    }
}
