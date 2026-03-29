<?php

namespace App\Http\Controllers;

use App\Jobs\UpdateWalletPassJob;
use App\Models\AppleWalletRegistration;
use App\Models\LoyaltyAccount;
use App\Models\StampEvent;
use App\Models\SupportAuditLog;
use App\Services\Support\MerchantRecoveryService;
use App\Services\Support\SupportAuditService;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MerchantCustomersController extends Controller
{
    public function __construct(
        protected SupportAuditService $supportAuditService,
        protected MerchantRecoveryService $merchantRecoveryService
    ) {
    }

    public function index(Request $request)
    {
        // Load merchant stores list
        $stores = Auth::user()->stores()->orderBy('name')->get(['id', 'name']);
        
        // Build store scope
        $storeIds = $stores->pluck('id');
        
        // Base query
        $query = LoyaltyAccount::query()
            ->whereIn('store_id', $storeIds)
            ->with(['customer', 'store'])
            ->latest('id');
        
        // Optional store filter
        $storeId = $request->input('store_id');
        if ($storeId) {
            // Verify the store belongs to the merchant
            if (!$storeIds->contains($storeId)) {
                abort(404, 'Store not found or you do not have access to it.');
            }
            $query->where('store_id', $storeId);
        }
        
        // Search filter
        $searchTerm = $request->input('q');
        if ($searchTerm) {
            $normalizedSearch = strtoupper(trim($searchTerm));

            $query->where(function ($query) use ($searchTerm, $normalizedSearch) {
                $query->whereHas('customer', function ($q) use ($searchTerm) {
                    $q->where('name', 'like', "%{$searchTerm}%")
                        ->orWhere('email', 'like', "%{$searchTerm}%")
                        ->orWhere('phone', 'like', "%{$searchTerm}%");
                })
                ->orWhere('manual_entry_code', $normalizedSearch)
                ->orWhere('public_token', $searchTerm);
            });
        }
        
        // Pagination
        $accounts = $query->paginate(25)->withQueryString();
        
        return view('merchant.customers.index', [
            'stores' => $stores,
            'activeStoreId' => $storeId,
            'q' => $searchTerm,
            'accounts' => $accounts,
        ]);
    }
    
    public function show(LoyaltyAccount $loyaltyAccount)
    {
        // Ensure loyalty account belongs to merchant
        $storeIds = Auth::user()->stores()->pluck('id');
        
        // Check if the account's store belongs to the merchant
        if (!$storeIds->contains($loyaltyAccount->store_id)) {
            return $this->supportAccessRedirect();
        }
        
        // Load relationships
        $account = $loyaltyAccount->load(['customer', 'store']);
        
        // Load recent events
        $events = StampEvent::where('loyalty_account_id', $account->id)
            ->with('user')
            ->latest()
            ->limit(50)
            ->get();

        $recentRegistrations = AppleWalletRegistration::where('loyalty_account_id', $account->id)
            ->active()
            ->orderByDesc('last_registered_at')
            ->limit(5)
            ->get();

        $walletStatus = [
            'active_apple_registrations' => AppleWalletRegistration::where('loyalty_account_id', $account->id)->active()->count(),
            'total_apple_registrations' => AppleWalletRegistration::where('loyalty_account_id', $account->id)->count(),
            'last_registered_at' => AppleWalletRegistration::where('loyalty_account_id', $account->id)->active()->max('last_registered_at'),
            'latest_card_change_at' => $account->updated_at,
        ];

        $walletStatus['next_action'] = $walletStatus['active_apple_registrations'] === 0
            ? 'No active Apple Wallet device registration is on file yet. Ask the customer to open or re-add the pass, then try a refresh again.'
            : 'If the customer says the pass looks stale, queue a wallet refresh first, then ask them to reopen Wallet before removing the pass.';

        $supportTimeline = collect();

        foreach ($events->take(10) as $event) {
            $supportTimeline->push([
                'title' => $event->type === 'redeem' ? 'Reward redeemed' : 'Stamp update',
                'detail' => ($event->type === 'redeem'
                    ? 'Redeem'
                    : 'Stamp') . ' recorded' . ($event->count ? ' for ' . $event->count : '') . ($event->user ? ' by ' . $event->user->name : ''),
                'at' => $event->created_at,
                'tone' => $event->type === 'redeem' ? 'text-accent-700' : 'text-brand-700',
            ]);
        }

        if ($account->email_verification_sent_at) {
            $supportTimeline->push([
                'title' => 'Verification email sent',
                'detail' => 'Most recent verification email request for this card.',
                'at' => $account->email_verification_sent_at,
                'tone' => 'text-amber-700',
            ]);
        }

        if ($account->verified_at) {
            $supportTimeline->push([
                'title' => 'Email verified',
                'detail' => 'The customer can redeem rewards without email verification blocking them.',
                'at' => $account->verified_at,
                'tone' => 'text-emerald-700',
            ]);
        }

        foreach ($recentRegistrations as $registration) {
            if ($registration->last_registered_at) {
                $supportTimeline->push([
                    'title' => 'Apple Wallet registered',
                    'detail' => 'A device registered this pass for Apple Wallet updates.',
                    'at' => $registration->last_registered_at,
                    'tone' => 'text-stone-700',
                ]);
            }
        }

        $supportLogs = SupportAuditLog::where('loyalty_account_id', $account->id)
            ->latest()
            ->limit(10)
            ->get();

        foreach ($supportLogs as $log) {
            $supportTimeline->push([
                'title' => str($log->event_type)->replace('_', ' ')->title()->toString(),
                'detail' => $log->message ?: 'Support event recorded.',
                'at' => $log->created_at,
                'tone' => $log->status === 'failed'
                    ? 'text-accent-700'
                    : ($log->status === 'blocked' || $log->status === 'partial' ? 'text-amber-700' : 'text-stone-700'),
            ]);
        }

        $supportTimeline = $supportTimeline
            ->sortByDesc(fn (array $item) => optional($item['at'])->timestamp ?? 0)
            ->values();
        
        return view('merchant.customers.show', [
            'account' => $account,
            'events' => $events,
            'walletStatus' => $walletStatus,
            'supportTimeline' => $supportTimeline,
        ]);
    }
    
    public function edit(LoyaltyAccount $loyaltyAccount)
    {
        // Ensure loyalty account belongs to merchant
        $storeIds = Auth::user()->stores()->pluck('id');
        
        // Check if the account's store belongs to the merchant
        if (!$storeIds->contains($loyaltyAccount->store_id)) {
            return $this->supportAccessRedirect();
        }
        
        // Load relationships
        $account = $loyaltyAccount->load(['customer', 'store']);
        
        return view('merchant.customers.edit', [
            'account' => $account,
        ]);
    }
    
    public function update(Request $request, LoyaltyAccount $loyaltyAccount)
    {
        // Ensure loyalty account belongs to merchant
        $storeIds = Auth::user()->stores()->pluck('id');
        
        // Check if the account's store belongs to the merchant
        if (!$storeIds->contains($loyaltyAccount->store_id)) {
            return $this->supportAccessRedirect();
        }
        
        // Load customer
        $customer = $loyaltyAccount->customer;
        
        // Validate input
        $validated = $request->validate([
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name'  => ['nullable', 'string', 'max:255'],
            'email'      => ['nullable', 'email', 'max:255'],
            'phone'      => ['nullable', 'string', 'max:255'],
            'birthday'   => ['nullable', 'date'],
        ]);

        // Keep the denormalised `name` column in sync
        $firstName = trim($validated['first_name'] ?? $customer->first_name ?? '');
        $lastName  = trim($validated['last_name']  ?? $customer->last_name  ?? '');
        $validated['name'] = trim("$firstName $lastName") ?: $customer->name;

        // Update customer data
        $customer->update($validated);
        
        return redirect()
            ->route('merchant.customers.show', $loyaltyAccount)
            ->with('success', 'Customer information updated successfully.');
    }

    public function resendVerification(Request $request, LoyaltyAccount $loyaltyAccount)
    {
        $storeIds = Auth::user()->stores()->pluck('id');

        if (!$storeIds->contains($loyaltyAccount->store_id)) {
            return $this->supportAccessRedirect();
        }

        $this->supportAuditService->log(
            eventType: 'manual_support_action',
            status: 'success',
            storeId: $loyaltyAccount->store_id,
            loyaltyAccountId: $loyaltyAccount->id,
            actorUserId: $request->user()?->id,
            source: 'merchant',
            message: 'Merchant requested a verification resend from customer support view.',
            metadata: [
                'action' => 'resend_verification',
            ]
        );

        return app(CustomerEmailVerificationController::class)->send($request, $loyaltyAccount->public_token);
    }

    public function resendWelcomeEmail(LoyaltyAccount $loyaltyAccount)
    {
        $storeIds = Auth::user()->stores()->pluck('id');

        if (!$storeIds->contains($loyaltyAccount->store_id)) {
            return $this->supportAccessRedirect();
        }

        try {
            $this->merchantRecoveryService->resendWelcomeEmail($loyaltyAccount, Auth::id());

            return back()->with('success', 'Welcome email resent. The customer should receive a fresh join and verification message shortly.');
        } catch (\RuntimeException $e) {
            return back()->withErrors(['support' => $e->getMessage()]);
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['support' => 'The welcome email could not be queued right now. Please try again shortly.']);
        }
    }

    public function syncWallet(LoyaltyAccount $loyaltyAccount)
    {
        $storeIds = Auth::user()->stores()->pluck('id');

        if (!$storeIds->contains($loyaltyAccount->store_id)) {
            return $this->supportAccessRedirect();
        }

        UpdateWalletPassJob::dispatch($loyaltyAccount->id);

        $this->supportAuditService->log(
            eventType: 'manual_support_action',
            status: 'success',
            storeId: $loyaltyAccount->store_id,
            loyaltyAccountId: $loyaltyAccount->id,
            actorUserId: Auth::id(),
            source: 'merchant',
            message: 'Merchant queued a wallet refresh from customer support view.',
            metadata: [
                'action' => 'sync_wallet',
            ]
        );

        return back()->with('success', 'Wallet refresh queued. Apple and Google Wallet will update on the next sync cycle.');
    }

    private function supportAccessRedirect()
    {
        return redirect()
            ->route('merchant.customers.index')
            ->withErrors([
                'support' => 'We could not open that customer record. It may belong to a different store, or it may no longer be available.',
            ]);
    }
}
