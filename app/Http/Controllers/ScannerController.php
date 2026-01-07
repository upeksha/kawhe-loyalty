<?php

namespace App\Http\Controllers;

use App\Events\StampUpdated;
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
            'count' => 'nullable|integer|min:1|max:100',
        ]);

        $token = $request->token;
        // Strip "LA:" prefix if present
        if (Str::startsWith($token, 'LA:')) {
            $token = Str::substr($token, 3);
        }

        $storeId = $request->store_id;
        $count = $request->input('count', 1);

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
        $account->increment('stamp_count', $count);
        $account->last_stamped_at = now();

        // Reset the "Redeemed" state if we are starting a new cycle
        if (!is_null($account->reward_redeemed_at) && $account->stamp_count > 0 && $account->stamp_count < $store->reward_target) {
             $account->reward_redeemed_at = null;
        }

        // Check for reward availability
        if ($account->stamp_count >= $store->reward_target && is_null($account->reward_available_at)) {
            $account->reward_available_at = now();
            $account->redeem_token = Str::random(40);
            // Also ensure previous redeemed status is cleared if we hit target immediately
            $account->reward_redeemed_at = null; 
        }

        $account->save();
        
        // Refresh account with relationships before broadcasting
        $account->refresh();
        $account->load(['store', 'customer']);

        // Dispatch real-time event
        \Log::info('Dispatching StampUpdated event (stamp)', [
            'public_token' => $account->public_token,
            'channel' => 'loyalty-card.' . $account->public_token,
            'stamp_count' => $account->stamp_count
        ]);
        
        StampUpdated::dispatch($account);

        // Record event
        StampEvent::create([
            'loyalty_account_id' => $account->id,
            'store_id' => $store->id,
            'user_id' => Auth::id(),
            'type' => 'stamp',
            'count' => $count,
        ]);

        return response()->json([
            'success' => true,
            'storeName' => $store->name,
            'customerLabel' => $account->customer->name ?? 'Customer',
            'stampCount' => $account->stamp_count,
            'rewardTarget' => $store->reward_target,
            'rewardAvailable' => !is_null($account->reward_available_at),
        ]);
    }

    public function redeem(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'store_id' => 'required|exists:stores,id',
        ]);

        $token = $request->token;
        // Strip "REDEEM:" prefix if present
        if (Str::startsWith($token, 'REDEEM:')) {
            $token = Str::substr($token, 7);
        }

        $storeId = $request->store_id;

        // Verify user owns the store
        $store = Auth::user()->stores()->where('id', $storeId)->first();
        if (!$store) {
            abort(403, 'You do not own this store.');
        }

        // Find loyalty account by redeem_token
        $account = LoyaltyAccount::where('redeem_token', $token)
            ->where('store_id', $storeId)
            ->with(['customer'])
            ->first();

        if (!$account) {
            throw ValidationException::withMessages(['token' => 'Invalid or expired redemption code.']);
        }

        if (!is_null($account->reward_redeemed_at)) {
            throw ValidationException::withMessages(['token' => 'Reward already redeemed.']);
        }

        // Process redemption
        // Keep reward_redeemed_at for history but clear it for the next cycle
        // Or if we want to show "Last Redeemed" permanently until next reward, we need a separate field or logic.
        // For simplicity: We set reward_redeemed_at to now, BUT we must ensure the NEXT stamp clears it 
        // so the cycle restarts cleanly.
        
        $account->reward_redeemed_at = now();
        $account->redeem_token = null; // Invalidate token immediately
        $account->stamp_count = max(0, $account->stamp_count - $store->reward_target); // Deduct stamps
        $account->reward_available_at = null; // Reset availability
        $account->save();
        
        // Refresh account with relationships before broadcasting
        $account->refresh();
        $account->load(['store', 'customer']);

        // Dispatch real-time event
        \Log::info('Dispatching StampUpdated event (redeem)', [
            'public_token' => $account->public_token,
            'channel' => 'loyalty-card.' . $account->public_token,
            'stamp_count' => $account->stamp_count
        ]);
        
        StampUpdated::dispatch($account);

        // Record event
        StampEvent::create([
            'loyalty_account_id' => $account->id,
            'store_id' => $store->id,
            'user_id' => Auth::id(),
            'type' => 'redeem',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Reward redeemed successfully!',
            'customerLabel' => $account->customer->name ?? 'Customer',
        ]);
    }
}
