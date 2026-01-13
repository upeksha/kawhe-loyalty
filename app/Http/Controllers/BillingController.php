<?php

namespace App\Http\Controllers;

use App\Services\Billing\UsageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Stripe;
use Stripe\Checkout\Session as StripeCheckoutSession;
use Stripe\Subscription as StripeSubscription;

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
            'stripePriceId' => config('cashier.price_id'),
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
        $priceId = config('cashier.price_id');

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
            $appUrl = config('app.url');
            $checkout = $user->newSubscription('default', $priceId)
                ->checkout([
                    'success_url' => $appUrl . '/billing/success?session_id={CHECKOUT_SESSION_ID}',
                    'cancel_url' => route('billing.cancel'),
                    'client_reference_id' => (string) $user->id,
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
     * Handles checkout session retrieval and subscription sync.
     */
    public function success(Request $request)
    {
        $sessionId = $request->query('session_id');
        
        if (!$sessionId) {
            Log::warning('Billing success page accessed without session_id', [
                'user_id' => $request->user()?->id,
                'ip' => $request->ip(),
            ]);
            
            return view('billing.success', [
                'error' => 'No session ID provided. Please check your subscription status on the billing page.',
                'hasSession' => false,
            ]);
        }
        
        try {
            // Set Stripe API key
            Stripe::setApiKey(config('cashier.secret'));
            
            // Retrieve checkout session with expanded subscription and customer
            $session = StripeCheckoutSession::retrieve([
                'id' => $sessionId,
                'expand' => ['subscription', 'customer', 'line_items'],
            ], [
                'stripe_account' => null,
            ]);
            
            Log::info('Checkout session retrieved', [
                'session_id' => $sessionId,
                'status' => $session->status,
                'payment_status' => $session->payment_status,
                'customer_id' => $session->customer,
                'subscription_id' => $session->subscription,
            ]);
            
            // Check if payment is complete
            if ($session->status !== 'complete') {
                return view('billing.success', [
                    'message' => 'Payment is still processing. Please refresh this page in a few moments.',
                    'hasSession' => true,
                    'sessionStatus' => $session->status,
                    'canRetry' => true,
                    'sessionId' => $sessionId,
                ]);
            }
            
            // Get the user - try by client_reference_id first, then by customer email
            $user = null;
            if ($session->client_reference_id) {
                $user = \App\Models\User::find($session->client_reference_id);
            }
            
            if (!$user && $session->customer) {
                // Try to find user by Stripe customer ID
                $user = \App\Models\User::where('stripe_id', $session->customer)->first();
            }
            
            if (!$user && $session->customer_details && $session->customer_details->email) {
                // Fallback: find by email
                $user = \App\Models\User::where('email', $session->customer_details->email)->first();
            }
            
            if (!$user) {
                Log::error('Could not find user for checkout session', [
                    'session_id' => $sessionId,
                    'client_reference_id' => $session->client_reference_id,
                    'customer_id' => $session->customer,
                    'customer_email' => $session->customer_details->email ?? null,
                ]);
                
                return view('billing.success', [
                    'error' => 'Could not identify your account. Please contact support with your payment confirmation.',
                    'hasSession' => true,
                    'sessionId' => $sessionId,
                ]);
            }
            
            // Ensure user is authenticated or matches the session
            if ($request->user() && $request->user()->id !== $user->id) {
                Log::warning('Session user mismatch', [
                    'session_user_id' => $user->id,
                    'authenticated_user_id' => $request->user()->id,
                ]);
            }
            
            // Sync subscription from Stripe
            if ($session->subscription) {
                try {
                    // Ensure user has Stripe customer ID
                    if (!$user->hasStripeId()) {
                        $user->stripe_id = is_string($session->customer) ? $session->customer : $session->customer->id;
                        $user->save();
                        Log::info('Set Stripe customer ID for user', [
                            'user_id' => $user->id,
                            'stripe_id' => $user->stripe_id,
                        ]);
                    }
                    
                    // Get subscription ID (handle both string and object)
                    $subscriptionId = is_string($session->subscription) ? $session->subscription : $session->subscription->id;
                    
                    // Try Cashier's sync method first
                    try {
                        $user->syncStripeSubscriptions();
                        Log::info('Cashier syncStripeSubscriptions called', [
                            'user_id' => $user->id,
                            'subscription_id' => $subscriptionId,
                        ]);
                    } catch (\Exception $syncError) {
                        Log::warning('syncStripeSubscriptions failed, trying direct retrieval', [
                            'user_id' => $user->id,
                            'error' => $syncError->getMessage(),
                        ]);
                    }
                    
                    // Fallback: Directly retrieve and create/update subscription
                    try {
                        $stripeSubscription = \Stripe\Subscription::retrieve($subscriptionId);
                        
                        // Create or update subscription record manually
                        $subscription = \Laravel\Cashier\Subscription::updateOrCreate(
                            [
                                'stripe_id' => $stripeSubscription->id,
                            ],
                            [
                                'user_id' => $user->id,
                                'type' => 'default',
                                'stripe_status' => $stripeSubscription->status,
                                'stripe_price' => $stripeSubscription->items->data[0]->price->id ?? null,
                                'quantity' => $stripeSubscription->items->data[0]->quantity ?? null,
                                'trial_ends_at' => $stripeSubscription->trial_end ? \Carbon\Carbon::createFromTimestamp($stripeSubscription->trial_end) : null,
                                'ends_at' => $stripeSubscription->cancel_at ? \Carbon\Carbon::createFromTimestamp($stripeSubscription->cancel_at) : ($stripeSubscription->cancel_at_period_end && $stripeSubscription->current_period_end ? \Carbon\Carbon::createFromTimestamp($stripeSubscription->current_period_end) : null),
                            ]
                        );
                        
                        // Sync subscription items
                        foreach ($stripeSubscription->items->data as $item) {
                            $subscription->items()->updateOrCreate(
                                [
                                    'stripe_id' => $item->id,
                                ],
                                [
                                    'stripe_product' => $item->price->product,
                                    'stripe_price' => $item->price->id,
                                    'quantity' => $item->quantity ?? null,
                                ]
                            );
                        }
                        
                        Log::info('Subscription manually synced after checkout', [
                            'user_id' => $user->id,
                            'subscription_id' => $subscriptionId,
                            'status' => $stripeSubscription->status,
                            'db_subscription_id' => $subscription->id,
                        ]);
                    } catch (\Exception $directError) {
                        Log::error('Direct subscription retrieval also failed', [
                            'user_id' => $user->id,
                            'subscription_id' => $subscriptionId,
                            'error' => $directError->getMessage(),
                        ]);
                        throw $directError;
                    }
                    
                    // Verify subscription is now active
                    $subscription = $user->subscription('default');
                    if ($subscription && in_array($subscription->stripe_status, ['active', 'trialing'])) {
                        Log::info('Subscription verified as active, redirecting to dashboard', [
                            'user_id' => $user->id,
                            'subscription_status' => $subscription->stripe_status,
                        ]);
                        return redirect()->route('merchant.dashboard')
                            ->with('success', 'Your Pro plan subscription has been activated! You can now create unlimited loyalty cards.');
                    }
                    
                    Log::warning('Subscription synced but status not active/trialing', [
                        'user_id' => $user->id,
                        'subscription_status' => $subscription ? $subscription->stripe_status : 'null',
                    ]);
                    
                    return view('billing.success', [
                        'message' => 'Subscription is being activated. This may take a few moments. Please refresh the billing page to check your status.',
                        'hasSession' => true,
                        'canRetry' => true,
                        'sessionId' => $sessionId,
                    ]);
                    
                } catch (\Exception $e) {
                    Log::error('Failed to sync subscription after checkout', [
                        'user_id' => $user->id,
                        'session_id' => $sessionId,
                        'subscription_id' => is_string($session->subscription) ? $session->subscription : ($session->subscription->id ?? 'unknown'),
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    
                    return view('billing.success', [
                        'error' => 'Payment was successful, but we encountered an issue syncing your subscription. Please use the "Sync Subscription" button on the billing page or contact support.',
                        'hasSession' => true,
                        'canRetry' => true,
                        'sessionId' => $sessionId,
                    ]);
                }
            } else {
                // Async payment method (e.g., Klarna) - subscription will be created later
                Log::info('Checkout session complete but no subscription yet (async payment)', [
                    'user_id' => $user->id,
                    'session_id' => $sessionId,
                    'payment_status' => $session->payment_status,
                ]);
                
                return view('billing.success', [
                    'message' => 'Your payment is being processed. Your subscription will be activated once payment is confirmed. This may take a few minutes for some payment methods.',
                    'hasSession' => true,
                    'canRetry' => true,
                    'isAsyncPayment' => true,
                    'sessionId' => $sessionId,
                ]);
            }
            
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            Log::error('Invalid Stripe checkout session', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);
            
            return view('billing.success', [
                'error' => 'Invalid session. Please check your subscription status on the billing page.',
                'hasSession' => false,
            ]);
        } catch (\Exception $e) {
            Log::error('Error processing checkout success', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return view('billing.success', [
                'error' => 'An error occurred while processing your subscription. Please contact support or try syncing from the billing page.',
                'hasSession' => true,
                'canRetry' => true,
                'sessionId' => $sessionId,
            ]);
        }
    }
    
    /**
     * Manual sync endpoint for subscription status.
     * Can sync by session_id OR by user's Stripe customer ID.
     * Idempotent - safe to call multiple times.
     */
    public function sync(Request $request)
    {
        $request->validate([
            'session_id' => 'nullable|string',
        ]);
        
        $sessionId = $request->input('session_id');
        $user = $request->user();
        
        try {
            Stripe::setApiKey(config('cashier.secret'));
            
            $subscriptionId = null;
            
            // If session_id provided, retrieve from session
            if ($sessionId) {
                $session = StripeCheckoutSession::retrieve([
                    'id' => $sessionId,
                    'expand' => ['subscription', 'customer'],
                ]);
                
                // Verify this session belongs to the authenticated user
                $sessionUserId = $session->client_reference_id;
                if ($sessionUserId && (string) $user->id !== $sessionUserId) {
                    return back()->withErrors(['error' => 'This session does not belong to your account.']);
                }
                
                // Ensure user has Stripe customer ID
                if (!$user->hasStripeId() && $session->customer) {
                    $user->stripe_id = is_string($session->customer) ? $session->customer : $session->customer->id;
                    $user->save();
                }
                
                if ($session->subscription) {
                    $subscriptionId = is_string($session->subscription) ? $session->subscription : $session->subscription->id;
                }
            }
            
            // If no subscription from session, try to find from user's Stripe customer
            if (!$subscriptionId && $user->hasStripeId()) {
                try {
                    $stripeCustomer = \Stripe\Customer::retrieve($user->stripe_id);
                    $subscriptions = \Stripe\Subscription::all([
                        'customer' => $user->stripe_id,
                        'status' => 'all',
                        'limit' => 10,
                    ]);
                    
                    if ($subscriptions->data && count($subscriptions->data) > 0) {
                        // Get the most recent active subscription
                        $activeSub = collect($subscriptions->data)->firstWhere('status', 'active');
                        $trialingSub = collect($subscriptions->data)->firstWhere('status', 'trialing');
                        $subscriptionId = $activeSub ? $activeSub->id : ($trialingSub ? $trialingSub->id : $subscriptions->data[0]->id);
                        
                        Log::info('Found subscription from Stripe customer', [
                            'user_id' => $user->id,
                            'subscription_id' => $subscriptionId,
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to retrieve subscriptions from Stripe customer', [
                        'user_id' => $user->id,
                        'stripe_id' => $user->stripe_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            
            if ($subscriptionId) {
                // Get subscription ID (handle both string and object)
                $subscriptionId = is_string($session->subscription) ? $session->subscription : $session->subscription->id;
                
                // Ensure user has Stripe customer ID
                if (!$user->hasStripeId()) {
                    $user->stripe_id = is_string($session->customer) ? $session->customer : $session->customer->id;
                    $user->save();
                }
                
                // Try Cashier's sync method first
                try {
                    $user->syncStripeSubscriptions();
                    Log::info('Cashier syncStripeSubscriptions called (manual sync)', [
                        'user_id' => $user->id,
                        'subscription_id' => $subscriptionId,
                    ]);
                } catch (\Exception $syncError) {
                    Log::warning('syncStripeSubscriptions failed, trying direct retrieval (manual sync)', [
                        'user_id' => $user->id,
                        'error' => $syncError->getMessage(),
                    ]);
                }
                
                // Fallback: Directly retrieve and create/update subscription
                try {
                    $stripeSubscription = StripeSubscription::retrieve($subscriptionId);
                    
                    $subscription = \Laravel\Cashier\Subscription::updateOrCreate(
                        [
                            'stripe_id' => $stripeSubscription->id,
                        ],
                        [
                            'user_id' => $user->id,
                            'type' => 'default',
                            'stripe_status' => $stripeSubscription->status,
                            'stripe_price' => $stripeSubscription->items->data[0]->price->id ?? null,
                            'quantity' => $stripeSubscription->items->data[0]->quantity ?? null,
                            'trial_ends_at' => $stripeSubscription->trial_end ? \Carbon\Carbon::createFromTimestamp($stripeSubscription->trial_end) : null,
                            'ends_at' => $stripeSubscription->cancel_at ? \Carbon\Carbon::createFromTimestamp($stripeSubscription->cancel_at) : ($stripeSubscription->cancel_at_period_end && $stripeSubscription->current_period_end ? \Carbon\Carbon::createFromTimestamp($stripeSubscription->current_period_end) : null),
                        ]
                    );
                    
                    // Sync subscription items
                    foreach ($stripeSubscription->items->data as $item) {
                        $subscription->items()->updateOrCreate(
                            [
                                'stripe_id' => $item->id,
                            ],
                            [
                                'stripe_product' => $item->price->product,
                                'stripe_price' => $item->price->id,
                                'quantity' => $item->quantity ?? null,
                            ]
                        );
                    }
                    
                    Log::info('Manual subscription sync completed (direct method)', [
                        'user_id' => $user->id,
                        'session_id' => $sessionId,
                        'subscription_id' => $subscriptionId,
                        'status' => $stripeSubscription->status,
                    ]);
                } catch (\Exception $directError) {
                    Log::error('Direct subscription sync failed (manual sync)', [
                        'user_id' => $user->id,
                        'session_id' => $sessionId,
                        'subscription_id' => $subscriptionId,
                        'error' => $directError->getMessage(),
                    ]);
                    throw $directError;
                }
                
                // Verify subscription exists
                $subscription = $user->subscription('default');
                if (!$subscription) {
                    throw new \Exception('Subscription not found after sync');
                }
                
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Subscription synced successfully',
                        'subscription_status' => $subscription->stripe_status,
                    ]);
                }
                
                return redirect()->route('billing.index')
                    ->with('success', 'Subscription status has been synced.');
            } else {
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Subscription not yet available. Payment may still be processing.',
                    ], 202);
                }
                
                return back()->with('info', 'Subscription is still being processed. Please try again in a few moments.');
            }
            
        } catch (\Exception $e) {
            Log::error('Manual sync failed', [
                'user_id' => $user->id,
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to sync subscription: ' . $e->getMessage(),
                ], 500);
            }
            
            return back()->withErrors(['error' => 'Failed to sync subscription. Please try again later.']);
        }
    }

    /**
     * Show cancel page after cancelled checkout.
     */
    public function cancel(Request $request)
    {
        return view('billing.cancel');
    }
}
