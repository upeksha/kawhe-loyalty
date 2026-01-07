<?php

namespace App\Http\Controllers;

use App\Models\LoyaltyAccount;
use App\Models\StampEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ScannerController extends Controller
{
    public function index()
    {
        $stores = Auth::user()->stores()->get();
        return view('scanner.index', compact('stores'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'store_id' => 'required|exists:stores,id',
        ]);

        $token = $request->token;
        // Strip "LA:" prefix if present
        if (Str::startsWith($token, 'LA:')) {
            $token = Str::substr($token, 3);
        }

        $storeId = $request->store_id;

        // Verify user owns the store
        $store = Auth::user()->stores()->where('id', $storeId)->first();
        if (!$store) {
            abort(403, 'You do not own this store.');
        }

        // Find loyalty account
        $account = LoyaltyAccount::where('public_token', $token)
            ->where('store_id', $storeId)
            ->with(['customer', 'store'])
            ->first();

        if (!$account) {
            throw ValidationException::withMessages(['token' => 'Invalid loyalty card for this store.']);
        }

        // Cooldown check (30 seconds)
        if ($account->last_stamped_at && $account->last_stamped_at->diffInSeconds(now()) < 30) {
            throw ValidationException::withMessages(['token' => 'Already stamped recently. Please wait a moment.']);
        }

        // Increment stamp count
        $account->increment('stamp_count');
        $account->update(['last_stamped_at' => now()]);

        // Record event
        StampEvent::create([
            'loyalty_account_id' => $account->id,
            'store_id' => $store->id,
            'user_id' => Auth::id(),
            'type' => 'stamp',
        ]);

        return response()->json([
            'success' => true,
            'storeName' => $store->name,
            'customerLabel' => $account->customer->name ?? 'Customer',
            'stampCount' => $account->stamp_count,
            'rewardTarget' => $store->reward_target,
        ]);
    }
}
