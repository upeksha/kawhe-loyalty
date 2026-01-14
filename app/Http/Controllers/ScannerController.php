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
            'override_cooldown' => 'nullable|boolean', // Allow override of cooldown
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
        $overrideCooldown = $request->boolean('override_cooldown', false);

        // Capture logging information
        $userAgent = $request->userAgent();
        $ipAddress = $request->ip();

        // Use database transaction for atomicity
        return DB::transaction(function () use ($token, $requestedStoreId, $count, $idempotencyKey, $overrideCooldown, $userAgent, $ipAddress) {
            // Check if this idempotency key was already processed
            $existingEvent = StampEvent::where('idempotency_key', $idempotencyKey)->first();
            if ($existingEvent) {
                // Return the existing result (idempotent)
                $account = $existingEvent->loyaltyAccount;
                $account->load(['store', 'customer']);
                $store = $account->store;
                
                return response()->json([
                    'status' => 'duplicate',
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
            // Use conditional locking based on database driver
            $accountQuery = LoyaltyAccount::where('public_token', $token);
            if (DB::getDriverName() !== 'sqlite') {
                $accountQuery->lockForUpdate();
            }
            $account = $accountQuery->with(['customer', 'store'])->first();

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

            // SERVER-SIDE IDEMPOTENCY WINDOW CHECK (5 seconds)
            // Check if a stamp event was created for this account within the last 5 seconds
            $recentEvent = StampEvent::where('loyalty_account_id', $account->id)
                ->where('store_id', $storeId)
                ->where('type', 'stamp')
                ->where('created_at', '>=', now()->subSeconds(5))
                ->orderBy('created_at', 'desc')
                ->first();

            // Also check last_stamped_at as a fallback
            $secondsSinceLastStamp = $account->last_stamped_at 
                ? $account->last_stamped_at->diffInSeconds(now()) 
                : null;

            if ($recentEvent || ($secondsSinceLastStamp !== null && $secondsSinceLastStamp < 5)) {
                // Duplicate detected - return without creating events/transactions
                $account->refresh();
                $account->load(['store', 'customer']);
                
                return response()->json([
                    'status' => 'duplicate',
                    'success' => false,
                    'message' => 'Duplicate scan ignored',
                    'stampCount' => $account->stamp_count,
                    'rewardBalance' => $account->reward_balance ?? 0,
                    'rewardTarget' => $store->reward_target,
                    'seconds_since_last' => $secondsSinceLastStamp ?? 0,
                ], 200);
            }

            // COOLDOWN CHECK (30 seconds) - with override support
            $secondsSinceLastStamp = $account->last_stamped_at 
                ? $account->last_stamped_at->diffInSeconds(now()) 
                : null;

            if ($secondsSinceLastStamp !== null && $secondsSinceLastStamp < 30) {
                // Within cooldown period
                if (!$overrideCooldown) {
                    // Return structured cooldown response
                    $secondsRemaining = 30 - $secondsSinceLastStamp;
                    return response()->json([
                        'status' => 'cooldown',
                        'success' => false,
                        'message' => "Stamped {$secondsSinceLastStamp}s ago",
                        'seconds_since_last' => $secondsSinceLastStamp,
                        'cooldown_seconds' => 30,
                        'allow_override' => true,
                        'next_action' => 'confirm_override',
                        'stampCount' => $account->stamp_count,
                        'rewardBalance' => $account->reward_balance ?? 0,
                    ], 409); // HTTP 409 Conflict
                }
                // override_cooldown is true - proceed to stamp (but still subject to idempotency window above)
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
                'status' => 'success',
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

    /**
     * Get reward balance information from a redeem token.
     * Used by frontend to show quantity selector.
     */
    public function getRedeemInfo(Request $request)
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
            ->with(['customer', 'store'])
            ->first();

        if (!$account) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid redemption code.',
            ], 404);
        }

        $rewardBalance = $account->reward_balance ?? 0;

        return response()->json([
            'success' => true,
            'reward_balance' => $rewardBalance,
            'reward_title' => $store->reward_title,
            'customer_name' => $account->customer->name ?? 'Customer',
        ]);
    }

    public function redeem(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'store_id' => 'required|exists:stores,id',
            'quantity' => 'nullable|integer|min:1', // Number of rewards to redeem (default 1)
            'idempotency_key' => 'nullable|string|max:255', // Optional idempotency key from client
        ]);

        $token = $request->token;
        // Strip "REDEEM:" prefix if present
        if (Str::startsWith($token, 'REDEEM:')) {
            $token = Str::substr($token, 7);
        }

        $storeId = $request->store_id;
        $quantity = $request->input('quantity', 1); // Default to 1 for backward compatibility
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
        return DB::transaction(function () use ($token, $storeId, $quantity, $idempotencyKey, $store, $userAgent, $ipAddress) {
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

            // Check if rewards are available
            $rewardBalance = $account->reward_balance ?? 0;
            if ($rewardBalance <= 0) {
                throw ValidationException::withMessages([
                    'token' => 'No rewards available to redeem. Please earn more stamps to unlock rewards.'
                ]);
            }

            // Validate quantity doesn't exceed available rewards
            if ($quantity > $rewardBalance) {
                throw ValidationException::withMessages([
                    'quantity' => "Cannot redeem {$quantity} reward(s). Only {$rewardBalance} reward(s) available."
                ]);
            }

            // Store original values for ledger
            $originalVersion = $account->version;
            $rewardBalanceBefore = $rewardBalance;

            // Process redemption: consume the specified quantity of rewards
            $account->reward_balance = $rewardBalance - $quantity;
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
            // Using Option A: points = -reward_target * quantity (for historical consistency)
            PointsTransaction::create([
                'loyalty_account_id' => $account->id,
                'store_id' => $store->id,
                'user_id' => Auth::id(),
                'type' => 'redeem',
                'points' => -($store->reward_target * $quantity), // Negative for redemption (quantity * target stamps)
                'idempotency_key' => $idempotencyKey,
                'metadata' => [
                    'reward_balance_before' => $rewardBalanceBefore,
                    'reward_balance_after' => $account->reward_balance,
                    'rewards_redeemed' => $quantity,
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
            
            $message = $quantity > 1 
                ? "Successfully redeemed {$quantity} rewards! Enjoy your {$store->reward_title}!"
                : "Reward redeemed successfully! Enjoy your {$store->reward_title}!";
            
            return response()->json([
                'success' => true,
                'message' => $message,
                'customerLabel' => $account->customer->name ?? 'Customer',
                'receipt' => [
                    'transaction_id' => $transaction->id ?? null,
                    'timestamp' => now()->toIso8601String(),
                    'reward_title' => $store->reward_title,
                    'rewards_redeemed' => $quantity,
                    'remaining_rewards' => $account->reward_balance ?? 0,
                    'remaining_stamps' => $account->stamp_count,
                ],
            ]);
        });
    }
}
