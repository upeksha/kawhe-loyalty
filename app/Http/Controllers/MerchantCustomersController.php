<?php

namespace App\Http\Controllers;

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
            $query->whereHas('customer', function ($q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%")
                  ->orWhere('email', 'like', "%{$searchTerm}%")
                  ->orWhere('phone', 'like', "%{$searchTerm}%");
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
        
        return view('merchant.customers.show', [
            'account' => $account,
            'events' => $events,
        ]);
    }
}

