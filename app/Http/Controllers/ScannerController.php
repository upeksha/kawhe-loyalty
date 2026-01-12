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
            'store_id' => 'nullable|exists:stores,id', // Made optional for backwards compatibility
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

        $requestedStoreId = $request->store_id; // Store from dropdown (may be wrong)
        $count = $request->input('count', 1);
        $idempotencyKey = $request->input('idempotency_key', Str::uuid()->toString());

        // Capture logging information
        $userAgent = $request->userAgent();
        $ipAddress = $request->ip();

        // Use database transaction for atomicity
        return DB::transaction(function () use ($token, $requestedStoreId, $count, $idempotencyKey, $userAgent, $ipAddress) {
            // Check if this idempotency key was already processed
            $existingEvent = StampEvent::where('idempotency_key', $idempotencyKey)->first();
            if ($existingEvent) {
                // Return the existing result (idempotent)
                $account = $existingEvent->loyaltyAccount;
                $account->load(['store', 'customer']);
                $store = $account->store;
                
                return response()->json([
                    'success' => true,
                    'storeName' => $store->name,
                    'store_id_used' => $store->id,
                    'store_name_used' => $store->name,
                    'store_switched' => false,
                    'customerLabel' => $account->customer->name ?? 'Customer',
                    'stampCount' => $account->stamp_count,
                    'rewardBalance' => $account->reward_balance ?? 0,
                    'rewardTarget' => $store->reward_target,
                    'rewardAvailable' => ($account->reward_balance ?? 0) > 0,
                    'message' => 'Already processed',
                ]);
            }

            // STEP 1: Lookup loyalty account by public_token ONLY (no store filter)
            $account = LoyaltyAccount::where('public_token', $token)
                ->lockForUpdate()
                ->with(['customer', 'store'])
                ->first();

            if (!$account) {
                throw ValidationException::withMessages([
                    'token' => 'This loyalty card is invalid or not found. Please check the QR code and try again.'
                ]);
            }

            // STEP 2: Determine the store from the account
            $actualStore = $account->store;
            $actualStoreId = $actualStore->id;

            // STEP 3: Authorization - Check if merchant has access to this store
            $merchant = Auth::user();
            $merchantOwnsStore = $merchant->stores()->where('id', $actualStoreId)->exists();
            
            // Super admins can access any store
            if (!$merchantOwnsStore && !$merchant->isSuperAdmin()) {
                // Security: Don't expose store name if merchant doesn't have access
                throw ValidationException::withMessages([
                    'token' => 'This loyalty card belongs to a store you do not have access to. Please contact support if you believe this is an error.'
                ]);
            }

            // STEP 4: Determine if store was switched
            $storeSwitched = $requestedStoreId && $requestedStoreId != $actualStoreId;

            // STEP 5: Use the account's store for the transaction (ignore dropdown if different)
            $store = $actualStore;
            $storeId = $actualStoreId;

            // Cooldown check (30 seconds)
            if ($account->last_stamped_at && $account->last_stamped_at->diffInSeconds(now()) < 30) {
                $secondsRemaining = 30 - $account->last_stamped_at->diffInSeconds(now());
                throw ValidationException::withMessages([
                    'token' => "Please wait {$secondsRemaining} more second(s) before stamping again. This prevents accidental double-stamping."
                ]);
            }

            // Store original version for optimistic locking check
            $originalVersion = $account->version;
            $stampCountBefore = $account->stamp_count;
            $rewardBalanceBefore = $account->reward_balance ?? 0;
            
            // Increment stamp count
            $account->increment('stamp_count', $count);
            $account->last_stamped_at = now();
            $account->increment('version'); // Increment version for optimistic locking

            // Calculate newly earned rewards and update reward_balance
            $newStampCount = $account->stamp_count;
            $newlyEarned = intval(floor($newStampCount / $store->reward_target));
            $remainder = $newStampCount % $store->reward_target;
            
            // Update reward_balance and stamp_count
            $account->reward_balance = ($account->reward_balance ?? 0) + $newlyEarned;
            $account->stamp_count = $remainder;
            
            // Update reward_available_at and redeem_token based on reward_balance
            if ($account->reward_balance > 0) {
                // Ensure reward_available_at is set when rewards become available
                if (is_null($account->reward_available_at)) {
                    $account->reward_available_at = now();
                }
                // Ensure redeem_token exists
                if (is_null($account->redeem_token)) {
                    $account->redeem_token = Str::random(40);
                }
            } else {
                // No rewards available
                $account->reward_available_at = null;
                $account->redeem_token = null;
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
                    'stamp_count_before' => $stampCountBefore,
                    'stamp_count_after' => $account->stamp_count,
                    'reward_balance_before' => $rewardBalanceBefore,
                    'reward_balance_after' => $account->reward_balance,
                    'newly_earned_rewards' => $newlyEarned,
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
                'storeName' => $store->name, // Keep for backwards compatibility
                'store_id_used' => $store->id,
                'store_name_used' => $store->name,
                'store_switched' => $storeSwitched,
                'loyalty_account_id' => $account->id,
                'customerLabel' => $account->customer->name ?? 'Customer',
                    'stampCount' => $account->stamp_count,
                    'rewardBalance' => $account->reward_balance ?? 0,
                    'rewardTarget' => $store->reward_target,
                    'rewardAvailable' => ($account->reward_balance ?? 0) > 0,
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

            // Check if rewards are available
            $rewardBalance = $account->reward_balance ?? 0;
            if ($rewardBalance <= 0) {
                throw ValidationException::withMessages([
                    'token' => 'No rewards available to redeem. Please earn more stamps to unlock rewards.'
                ]);
            }

            // Store original values for ledger
            $originalVersion = $account->version;
            $rewardBalanceBefore = $rewardBalance;

            // Process redemption: consume exactly ONE reward
            $account->reward_balance = $rewardBalance - 1;
            $account->reward_redeemed_at = now(); // Last redeemed timestamp
            $account->increment('version'); // Increment version for optimistic locking
            
            // Update reward_available_at and redeem_token based on remaining balance
            if ($account->reward_balance > 0) {
                // Still have rewards, keep token and availability
                // redeem_token stays the same (one token can represent "redeem 1 reward")
            } else {
                // No rewards left
                $account->reward_available_at = null;
                $account->redeem_token = null;
            }
            
            // Do NOT deduct stamp_count - it represents progress toward next reward
            $account->save();
            
            // Create ledger entry for redemption
            // Using Option A: points = -reward_target (for historical consistency)
            PointsTransaction::create([
                'loyalty_account_id' => $account->id,
                'store_id' => $store->id,
                'user_id' => Auth::id(),
                'type' => 'redeem',
                'points' => -$store->reward_target, // Negative for redemption (one reward = target stamps)
                'idempotency_key' => $idempotencyKey,
                'metadata' => [
                    'reward_balance_before' => $rewardBalanceBefore,
                    'reward_balance_after' => $account->reward_balance,
                    'rewards_redeemed' => 1,
                    'stamp_count' => $account->stamp_count, // Unchanged
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
                    'rewards_redeemed' => 1,
                    'remaining_rewards' => $account->reward_balance ?? 0,
                    'remaining_stamps' => $account->stamp_count,
                ],
            ]);
        });
    }
}
