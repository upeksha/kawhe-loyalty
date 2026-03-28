<?php

namespace App\Http\Controllers;

use App\Jobs\UpdateWalletPassJob;
use App\Models\AppleWalletRegistration;
use App\Models\LoyaltyAccount;
use App\Models\StampEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MerchantCustomersController extends Controller
{
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
            abort(404, 'Loyalty account not found or you do not have access to it.');
        }
        
        // Load relationships
        $account = $loyaltyAccount->load(['customer', 'store']);
        
        // Load recent events
        $events = StampEvent::where('loyalty_account_id', $account->id)
            ->with('user')
            ->latest()
            ->limit(50)
            ->get();

        $walletStatus = [
            'active_apple_registrations' => AppleWalletRegistration::where('loyalty_account_id', $account->id)->active()->count(),
            'total_apple_registrations' => AppleWalletRegistration::where('loyalty_account_id', $account->id)->count(),
            'last_registered_at' => AppleWalletRegistration::where('loyalty_account_id', $account->id)->active()->max('last_registered_at'),
            'latest_card_change_at' => $account->updated_at,
        ];
        
        return view('merchant.customers.show', [
            'account' => $account,
            'events' => $events,
            'walletStatus' => $walletStatus,
        ]);
    }
    
    public function edit(LoyaltyAccount $loyaltyAccount)
    {
        // Ensure loyalty account belongs to merchant
        $storeIds = Auth::user()->stores()->pluck('id');
        
        // Check if the account's store belongs to the merchant
        if (!$storeIds->contains($loyaltyAccount->store_id)) {
            abort(404, 'Loyalty account not found or you do not have access to it.');
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
            abort(404, 'Loyalty account not found or you do not have access to it.');
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
            abort(404, 'Loyalty account not found or you do not have access to it.');
        }

        return app(CustomerEmailVerificationController::class)->send($request, $loyaltyAccount->public_token);
    }

    public function syncWallet(LoyaltyAccount $loyaltyAccount)
    {
        $storeIds = Auth::user()->stores()->pluck('id');

        if (!$storeIds->contains($loyaltyAccount->store_id)) {
            abort(404, 'Loyalty account not found or you do not have access to it.');
        }

        UpdateWalletPassJob::dispatch($loyaltyAccount->id);

        return back()->with('success', 'Wallet refresh queued. Apple and Google Wallet will update on the next sync cycle.');
    }
}
