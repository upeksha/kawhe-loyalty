<?php

namespace App\Http\Controllers;

use App\Services\Billing\UsageService;
use App\Services\Support\SupportAuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Stripe;
use Stripe\Checkout\Session as StripeCheckoutSession;
use Stripe\Subscription as StripeSubscription;

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
        
        // Debug info
        $debugInfo = [
            'has_stripe_id' => !empty($user->stripe_id),
            'stripe_id' => $user->stripe_id,
            'subscription_exists' => $subscription !== null,
            'subscription_status' => $subscription ? $subscription->stripe_status : null,
            'is_subscribed_check' => $user->subscribed('default'),
            'subscriptions_count' => $user->subscriptions()->count(),
        ];

        $billingDiagnostics = [
            [
                'label' => 'Stripe customer linked',
                'ready' => !empty($user->stripe_id),
                'hint' => 'Needed for portal access and reliable subscription sync.',
            ],
            [
                'label' => 'Subscription record present',
                'ready' => $subscription !== null,
                'hint' => 'If missing after checkout, run a sync from Stripe.',
            ],
            [
                'label' => 'Plan allows new joins',
                'ready' => (bool) ($stats['can_create_card'] ?? false),
                'hint' => 'New customer joins pause when free-plan capacity is full.',
            ],
            [
                'label' => 'Stripe configuration available',
                'ready' => !empty(config('cashier.key')) && !empty(config('cashier.secret')) && !empty(config('cashier.price_id')),
                'hint' => 'Needed for checkout, sync, and subscription management.',
            ],
        ];

        $planState = $this->buildPlanState($stats, $subscription);
        $recoveryActions = $this->buildRecoveryActions($user, $stats, $subscription);

        $recommendedBillingAction = !empty(config('cashier.key')) && !empty(config('cashier.secret')) && !empty(config('cashier.price_id'))
            ? ($subscription === null && !empty($user->stripe_id)
                ? 'Stripe knows this merchant, but no local subscription is visible yet. Run a sync from Stripe before escalating.'
                : ((!($stats['can_create_card'] ?? false) && !($stats['is_subscribed'] ?? false))
                    ? 'New joins are blocked by the free-plan cap. Upgrading is the fastest way to restore signups.'
                    : 'Billing looks healthy. If a merchant still reports issues, refresh subscription status first, then check Stripe Dashboard.'))
            : 'Stripe configuration is incomplete. Fix configuration first before testing checkout or sync behaviour.';

        return view('billing.index', [
            'stats' => $stats,
            'subscription' => $subscription,
            'stripePriceId' => config('cashier.price_id'),
            'debugInfo' => $debugInfo,
            'billingDiagnostics' => $billingDiagnostics,
            'recommendedBillingAction' => $recommendedBillingAction,
            'planState' => $planState,
            'recoveryActions' => $recoveryActions,
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
            
            $this->supportAuditService->log(
                eventType: 'billing_issue',
                status: 'failed',
                actorUserId: $user->id,
                source: 'billing.checkout',
                message: 'Checkout blocked because Stripe keys are not configured.'
            );
            return back()->withErrors([
                'error' => 'Checkout is not ready yet for this account. Please try syncing billing first, and contact support if the problem continues.'
            ]);
        }

        if (!$priceId) {
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
                'error' => 'The Pro plan checkout is not fully configured yet. Please contact support before trying again.'
            ]);
        }

        try {
            $appUrl = config('app.url');
            $checkout = $this->createCheckoutSession($user, $priceId, $appUrl);

            return redirect($checkout->url);
        } catch (\Exception $e) {
            if ($this->isMissingStripeCustomer($e) && !empty($user->stripe_id)) {
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
                'error' => 'We could not start checkout right now. Please try again, or use Sync Billing Status first if your account was recently upgraded.'
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
            if ($this->isMissingStripeCustomer($e) && !empty($user->stripe_id)) {
                Log::warning('Billing portal failed because stored Stripe customer is missing; clearing stale Stripe customer ID', [
                    'user_id' => $user->id,
                    'old_stripe_id' => $user->stripe_id,
                ]);

                $this->resetStripeCustomer($user);

                return back()->withErrors([
                    'error' => 'Your billing link was out of date after the Stripe account change. Please try again once more and a fresh billing profile will be created automatically.'
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
        
        if (!$sessionId) {
            Log::warning('Billing success page accessed without session_id', [
                'user_id' => $request->user()?->id,
                'ip' => $request->ip(),
            ]);
            
            return view('billing.success', [
                'error' => 'No session ID provided. Please check your subscription status on the billing page.',
                'hasSession' => false,
                'nextSteps' => $this->billingSuccessNextSteps('error'),
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
                    'nextSteps' => $this->billingSuccessNextSteps('processing'),
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
                    'nextSteps' => $this->billingSuccessNextSteps('error'),
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
                        'nextSteps' => $this->billingSuccessNextSteps('processing'),
                    ]);
                    
                } catch (\Exception $e) {
                    Log::error('Failed to sync subscription after checkout', [
                        'user_id' => $user->id,
                        'session_id' => $sessionId,
                        'subscription_id' => is_string($session->subscription) ? $session->subscription : ($session->subscription->id ?? 'unknown'),
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    
                    $this->supportAuditService->log(
                        eventType: 'billing_issue',
                        status: 'failed',
                        actorUserId: $user->id,
                        source: 'billing.success',
                        message: 'Payment succeeded but subscription sync failed.',
                        metadata: ['error' => $e->getMessage()]
                    );
                    return view('billing.success', [
                        'error' => 'Payment was successful, but we encountered an issue syncing your subscription. Please use the "Sync Subscription" button on the billing page or contact support.',
                        'hasSession' => true,
                        'canRetry' => true,
                        'sessionId' => $sessionId,
                        'nextSteps' => $this->billingSuccessNextSteps('error'),
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
                    'nextSteps' => $this->billingSuccessNextSteps('processing'),
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
                'nextSteps' => $this->billingSuccessNextSteps('error'),
            ]);
        } catch (\Exception $e) {
            Log::error('Error processing checkout success', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            if ($request->user()) {
                $this->supportAuditService->log(
                    eventType: 'billing_issue',
                    status: 'failed',
                    actorUserId: $request->user()->id,
                    source: 'billing.success',
                    message: 'Billing success page failed while processing session.',
                    metadata: ['error' => $e->getMessage()]
                );
            }
            return view('billing.success', [
                'error' => 'An error occurred while processing your subscription. Please contact support or try syncing from the billing page.',
                'hasSession' => true,
                'canRetry' => true,
                'sessionId' => $sessionId,
                'nextSteps' => $this->billingSuccessNextSteps('error'),
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
                    $this->supportAuditService->log(
                        eventType: 'billing_issue',
                        status: 'failed',
                        actorUserId: $user->id,
                        source: 'billing.sync',
                        message: 'Manual billing sync blocked because the session does not belong to the authenticated user.'
                    );
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
        $user = $request->user();
        $stats = $this->usageService->getUsageStats($user);

        return view('billing.cancel', [
            'stats' => $stats,
            'nextSteps' => [
                'Nothing has been charged. Your current plan stays exactly as it was.',
                ($stats['can_create_card'] ?? false)
                    ? 'You still have room to add customers on your current plan if you want to wait before upgrading.'
                    : 'If new joins are already blocked, you can return to billing anytime and restart checkout when you are ready.',
                'You can review usage and plan limits on the billing page before deciding again.',
            ],
        ]);
    }

    protected function buildPlanState(array $stats, $subscription): array
    {
        if ($subscription && in_array($subscription->stripe_status, ['active', 'trialing'], true) && ! $subscription->ends_at) {
            return [
                'label' => 'Pro active',
                'tone' => 'bg-emerald-100 text-emerald-700',
                'summary' => 'Unlimited joins are active and your customers can keep signing up without plan-based interruptions.',
                'transition' => 'If you cancel later, your current subscription stays active until the end of the billing period.',
            ];
        }

        if ($subscription && $subscription->ends_at) {
            return [
                'label' => 'Pro ending',
                'tone' => 'bg-amber-100 text-amber-700',
                'summary' => 'Your Pro plan is still active for now, but it is scheduled to end on ' . $subscription->ends_at->format('M d, Y') . '.',
                'transition' => 'After the billing period ends, new joins will fall back to your free-plan limit while existing customers keep their cards.',
            ];
        }

        if (! ($stats['can_create_card'] ?? false)) {
            return [
                'label' => 'Free plan full',
                'tone' => 'bg-accent-100 text-accent-700',
                'summary' => 'Your free-plan join limit is full, so new customers are currently blocked from joining.',
                'transition' => 'Upgrading restores new joins immediately. Existing customers and cards are not reset.',
            ];
        }

        return [
            'label' => 'Free plan active',
            'tone' => 'bg-stone-100 text-stone-700',
            'summary' => 'Your free plan is active and existing customers can continue using their cards normally.',
            'transition' => 'If you outgrow the free limit later, upgrading only changes join capacity. It does not remove existing customer data.',
        ];
    }

    protected function buildRecoveryActions($user, array $stats, $subscription): array
    {
        $actions = [];

        if ($subscription === null && !empty($user->stripe_id)) {
            $actions[] = 'Run a Stripe sync if checkout completed but your plan still looks unchanged.';
        }

        if (! ($stats['can_create_card'] ?? false) && ! ($stats['is_subscribed'] ?? false)) {
            $actions[] = 'Upgrade to Pro to reopen new customer signups right away.';
        }

        if ($subscription && $subscription->ends_at) {
            $actions[] = 'Open the billing portal if you want to keep Pro active beyond the current end date.';
        }

        if (empty(config('cashier.key')) || empty(config('cashier.secret')) || empty(config('cashier.price_id'))) {
            $actions[] = 'Stripe configuration needs attention before checkout or sync can work reliably.';
        }

        if (empty($actions)) {
            $actions[] = 'Billing looks healthy. If something still feels wrong, refresh status first and then review Stripe in the billing portal.';
        }

        return $actions;
    }

    protected function billingSuccessNextSteps(string $mode): array
    {
        return match ($mode) {
            'processing' => [
                'Return to billing and refresh status if the plan does not update automatically within a few minutes.',
                'Use manual sync only if checkout completed but the plan still looks unchanged.',
                'Existing customers can keep using their cards while payment finishes processing.',
            ],
            'error' => [
                'Go back to billing to refresh status before retrying checkout.',
                'If Stripe took payment but your plan still looks unchanged, use manual sync first.',
                'If the problem persists, contact support with your Stripe session or customer details.',
            ],
            default => [
                'Return to billing to review your current plan state.',
            ],
        };
    }

    protected function createCheckoutSession($user, string $priceId, string $appUrl)
    {
        return $user->newSubscription('default', $priceId)
            ->allowPromotionCodes()
            ->checkout([
                'success_url' => $appUrl . '/billing/success?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('billing.cancel'),
                'client_reference_id' => (string) $user->id,
            ]);
    }

    protected function refreshStripeCustomerState($user): void
    {
        try {
            if ($user->hasStripeId()) {
                $user->syncStripeCustomerDetails();
                $user->syncStripeSubscriptions();

                return;
            }
        } catch (\Exception $e) {
            if (! $this->isMissingStripeCustomer($e)) {
                throw $e;
            }

            Log::warning('Stored Stripe customer no longer exists in the active Stripe account; clearing stale Stripe ID before refresh', [
                'user_id' => $user->id,
                'old_stripe_id' => $user->stripe_id,
            ]);

            $this->resetStripeCustomer($user);
        }

        $user->createAsStripeCustomer();
        $user->syncStripeSubscriptions();
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
