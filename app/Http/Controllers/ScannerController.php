<?php

namespace App\Http\Controllers;

use App\Events\StampUpdated;
use App\Models\LoyaltyAccount;
use App\Models\PointsTransaction;
use App\Models\StampEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
            'idempotency_key' => 'nullable|string|max:255', // Optional idempotency key from client
        ]);

        $token = $request->token;
        // Strip "LA:" prefix if present
        if (Str::startsWith($token, 'LA:')) {
            $token = Str::substr($token, 3);
        }

        // Trim token to prevent whitespace issues
        $token = trim($token);

        $storeId = $request->store_id;
        $count = $request->input('count', 1);
        $idempotencyKey = $request->input('idempotency_key', Str::uuid()->toString());

        // Verify user owns the store
        $store = Auth::user()->stores()->where('id', $storeId)->first();
        if (!$store) {
            abort(403, 'You do not own this store.');
        }

        // Capture logging information
        $userAgent = $request->userAgent();
        $ipAddress = $request->ip();

        // Use database transaction for atomicity
        return DB::transaction(function () use ($token, $storeId, $count, $idempotencyKey, $store, $userAgent, $ipAddress) {
            // Check if this idempotency key was already processed
            $existingEvent = StampEvent::where('idempotency_key', $idempotencyKey)->first();
            if ($existingEvent) {
                // Return the existing result (idempotent)
                $account = $existingEvent->loyaltyAccount;
                $account->load(['store', 'customer']);
                
                return response()->json([
                    'success' => true,
                    'storeName' => $store->name,
                    'customerLabel' => $account->customer->name ?? 'Customer',
                    'stampCount' => $account->stamp_count,
                    'rewardTarget' => $store->reward_target,
                    'rewardAvailable' => !is_null($account->reward_available_at),
                    'message' => 'Already processed',
                ]);
            }

            // Find loyalty account with lock for update (pessimistic locking)
            $account = LoyaltyAccount::where('public_token', $token)
                ->where('store_id', $storeId)
                ->lockForUpdate()
                ->with(['customer', 'store'])
                ->first();

            if (!$account) {
                $potentialAccount = LoyaltyAccount::where('public_token', $token)->with('store')->first();
                $actualStoreName = $potentialAccount->store->name ?? 'Unknown Store';

                throw ValidationException::withMessages([
                    'token' => "This loyalty card belongs to '{$actualStoreName}' and is not valid for '{$store->name}'. Please ensure you have selected the correct store in the scanner."
                ]);
            }

            // Cooldown check (30 seconds)
            if ($account->last_stamped_at && $account->last_stamped_at->diffInSeconds(now()) < 30) {
                $secondsRemaining = 30 - $account->last_stamped_at->diffInSeconds(now());
                throw ValidationException::withMessages([
                    'token' => "Please wait {$secondsRemaining} more second(s) before stamping again. This prevents accidental double-stamping."
                ]);
            }

            // Store original version for optimistic locking check
            $originalVersion = $account->version;
            
            // Increment stamp count
            $account->increment('stamp_count', $count);
            $account->last_stamped_at = now();
            $account->increment('version'); // Increment version for optimistic locking

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
            
            // Create ledger entry (immutable audit trail)
            PointsTransaction::create([
                'loyalty_account_id' => $account->id,
                'store_id' => $store->id,
                'user_id' => Auth::id(),
                'type' => 'earn',
                'points' => $count,
                'idempotency_key' => $idempotencyKey,
                'metadata' => [
                    'stamp_count_before' => $account->stamp_count - $count,
                    'stamp_count_after' => $account->stamp_count,
                    'version_before' => $originalVersion,
                    'version_after' => $account->version,
                ],
                'user_agent' => $userAgent,
                'ip_address' => $ipAddress,
            ]);

            // Record event
            StampEvent::create([
                'loyalty_account_id' => $account->id,
                'store_id' => $store->id,
                'user_id' => Auth::id(),
                'type' => 'stamp',
                'count' => $count,
                'idempotency_key' => $idempotencyKey,
                'user_agent' => $userAgent,
                'ip_address' => $ipAddress,
            ]);
            
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

            // Get the transaction for receipt
            $transaction = PointsTransaction::where('idempotency_key', $idempotencyKey)->first();
            
            return response()->json([
                'success' => true,
                'message' => $count > 1 
                    ? "Successfully added {$count} stamps!" 
                    : "Successfully added 1 stamp!",
                'storeName' => $store->name,
                'customerLabel' => $account->customer->name ?? 'Customer',
                'stampCount' => $account->stamp_count,
                'rewardTarget' => $store->reward_target,
                'rewardAvailable' => !is_null($account->reward_available_at),
                'stampsRemaining' => max(0, $store->reward_target - $account->stamp_count),
                'receipt' => [
                    'transaction_id' => $transaction->id ?? null,
                    'timestamp' => now()->toIso8601String(),
                    'stamps_added' => $count,
                    'new_total' => $account->stamp_count,
                ],
            ]);
        });
    }

    public function redeem(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'store_id' => 'required|exists:stores,id',
            'idempotency_key' => 'nullable|string|max:255', // Optional idempotency key from client
        ]);

        $token = $request->token;
        // Strip "REDEEM:" prefix if present
        if (Str::startsWith($token, 'REDEEM:')) {
            $token = Str::substr($token, 7);
        }

        $storeId = $request->store_id;
        $idempotencyKey = $request->input('idempotency_key', Str::uuid()->toString());

        // Verify user owns the store
        $store = Auth::user()->stores()->where('id', $storeId)->first();
        if (!$store) {
            abort(403, 'You do not own this store.');
        }

        // Capture logging information
        $userAgent = $request->userAgent();
        $ipAddress = $request->ip();

        // Use database transaction for atomicity
        return DB::transaction(function () use ($token, $storeId, $idempotencyKey, $store, $userAgent, $ipAddress) {
            // Check if this idempotency key was already processed
            $existingEvent = StampEvent::where('idempotency_key', $idempotencyKey)
                ->where('type', 'redeem')
                ->first();
            if ($existingEvent) {
                // Return the existing result (idempotent)
                $account = $existingEvent->loyaltyAccount;
                $account->load(['store', 'customer']);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Reward already redeemed',
                    'customerLabel' => $account->customer->name ?? 'Customer',
                ]);
            }

            // Find loyalty account by redeem_token with lock
            $account = LoyaltyAccount::where('redeem_token', $token)
                ->where('store_id', $storeId)
                ->lockForUpdate()
                ->with(['customer'])
                ->first();

            if (!$account) {
                throw ValidationException::withMessages([
                    'token' => 'This redemption code is invalid or has expired. Please check the code and try again.'
                ]);
            }

            // Check if customer email is verified
            $account->load('customer');
            if (!$account->customer->email_verified_at) {
                throw ValidationException::withMessages([
                    'token' => 'You must verify your email address before you can redeem rewards. Please check your loyalty card page for verification options.'
                ]);
            }

            if (!is_null($account->reward_redeemed_at)) {
                $redeemedDate = $account->reward_redeemed_at->format('M d, Y \a\t g:i A');
                throw ValidationException::withMessages([
                    'token' => "This reward was already redeemed on {$redeemedDate}. Please earn a new reward to redeem again."
                ]);
            }

            // Store original values for ledger
            $originalVersion = $account->version;
            $stampCountBefore = $account->stamp_count;
            $pointsToDeduct = $store->reward_target;

            // Process redemption
            $account->reward_redeemed_at = now();
            $account->redeem_token = null; // Invalidate token immediately
            $account->stamp_count = max(0, $account->stamp_count - $pointsToDeduct); // Deduct stamps
            $account->reward_available_at = null; // Reset availability
            $account->increment('version'); // Increment version for optimistic locking
            $account->save();
            
            // Create ledger entry for redemption
            PointsTransaction::create([
                'loyalty_account_id' => $account->id,
                'store_id' => $store->id,
                'user_id' => Auth::id(),
                'type' => 'redeem',
                'points' => -$pointsToDeduct, // Negative for redemption
                'idempotency_key' => $idempotencyKey,
                'metadata' => [
                    'stamp_count_before' => $stampCountBefore,
                    'stamp_count_after' => $account->stamp_count,
                    'points_deducted' => $pointsToDeduct,
                    'version_before' => $originalVersion,
                    'version_after' => $account->version,
                ],
                'user_agent' => $userAgent,
                'ip_address' => $ipAddress,
            ]);
            
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
                'idempotency_key' => $idempotencyKey,
                'user_agent' => $userAgent,
                'ip_address' => $ipAddress,
            ]);

            // Get the transaction for receipt
            $transaction = PointsTransaction::where('idempotency_key', $idempotencyKey)->first();
            
            return response()->json([
                'success' => true,
                'message' => "Reward redeemed successfully! Enjoy your {$store->reward_title}!",
                'customerLabel' => $account->customer->name ?? 'Customer',
                'receipt' => [
                    'transaction_id' => $transaction->id ?? null,
                    'timestamp' => now()->toIso8601String(),
                    'reward_title' => $store->reward_title,
                    'points_deducted' => $pointsToDeduct,
                    'remaining_stamps' => $account->stamp_count,
                ],
            ]);
        });
    }
}
