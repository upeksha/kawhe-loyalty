<?php

namespace App\Http\Controllers;

use App\Services\Billing\UsageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BillingController extends Controller
{
    protected $usageService;

    public function __construct(UsageService $usageService)
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
                if ($user->hasStripeId()) {
                    $user->syncStripeCustomerDetails();
                    $user->syncStripeSubscriptions();
                } else {
                    // If user doesn't have Stripe ID yet, try to create customer
                    // This might happen if webhook hasn't processed yet
                    $user->createAsStripeCustomer();
                    $user->syncStripeSubscriptions();
                }
            } catch (\Exception $e) {
                Log::warning('Failed to sync subscription', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        $stats = $this->usageService->getUsageStats($user);
        $subscription = $user->subscription('default');
        
        // Debug info
        $debugInfo = [
            'has_stripe_id' => !empty($user->stripe_id),
            'stripe_id' => $user->stripe_id,
            'subscription_exists' => $subscription !== null,
            'subscription_status' => $subscription ? $subscription->stripe_status : null,
            'is_subscribed_check' => $user->subscribed('default'),
            'subscriptions_count' => $user->subscriptions()->count(),
        ];

        return view('billing.index', [
            'stats' => $stats,
            'subscription' => $subscription,
            'stripePriceId' => env('STRIPE_PRICE_ID'),
            'debugInfo' => $debugInfo,
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
        $priceId = config('cashier.price_id') ?? env('STRIPE_PRICE_ID');

        if (!$stripeKey || !$stripeSecret) {
            Log::error('Stripe keys not configured', [
                'user_id' => $user->id,
                'has_key' => !empty($stripeKey),
                'has_secret' => !empty($stripeSecret),
            ]);
            
            return back()->withErrors([
                'error' => 'Stripe is not configured. Please contact support or check your environment variables (STRIPE_KEY, STRIPE_SECRET).'
            ]);
        }

        if (!$priceId) {
            Log::error('Stripe price ID not configured', [
                'user_id' => $user->id,
            ]);
            
            return back()->withErrors([
                'error' => 'Stripe price ID not configured. Please contact support or check STRIPE_PRICE_ID in your environment variables.'
            ]);
        }

        try {
            $checkout = $user->newSubscription('default', $priceId)
                ->checkout([
                    'success_url' => route('billing.success'),
                    'cancel_url' => route('billing.cancel'),
                ]);

            return redirect($checkout->url);
        } catch (\Exception $e) {
            Log::error('Stripe checkout failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->withErrors([
                'error' => 'Failed to create checkout session: ' . $e->getMessage() . ' Please check your Stripe configuration.'
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
            Log::error('Stripe billing portal failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['error' => 'Failed to access billing portal. Please try again.']);
        }
    }

    /**
     * Show success page after subscription.
     */
    public function success(Request $request)
    {
        return view('billing.success');
    }

    /**
     * Show cancel page after cancelled checkout.
     */
    public function cancel(Request $request)
    {
        return view('billing.cancel');
    }
}
