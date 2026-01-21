<?php

namespace App\Http\Controllers;

use App\Events\StampUpdated;
use App\Models\LoyaltyAccount;
use App\Models\PointsTransaction;
use App\Models\StampEvent;
use App\Services\Loyalty\StampLoyaltyService;
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

    public function store(Request $request, StampLoyaltyService $stampService)
    {
        $request->validate([
            'token' => 'required|string',
            'store_id' => 'nullable|exists:stores,id', // Made optional for backwards compatibility
            'count' => 'nullable|integer|min:1|max:100',
            'idempotency_key' => 'nullable|string|max:255', // Optional idempotency key from client
            'override_cooldown' => 'nullable|boolean', // Allow override of cooldown
        ]);

        $token = $request->token;
        
        // Handle LR: (redeem) vs LA: (stamp) prefixes
        $isRedeem = false;
        if (Str::startsWith($token, 'LR:')) {
            $token = Str::substr($token, 3);
            $isRedeem = true;
        } elseif (Str::startsWith($token, 'LA:')) {
            $token = Str::substr($token, 3);
            $isRedeem = false;
        }

        // Trim token to prevent whitespace issues
        $token = trim($token);
        
        // If this is a redeem request, route to redeem method
        if ($isRedeem) {
            return $this->redeem($request->merge(['token' => $token]));
        }

        $requestedStoreId = $request->store_id; // Store from dropdown (may be wrong)
        $count = $request->input('count', 1);
        $incomingIdempotencyKey = $request->input('idempotency_key');
        $idempotencyKey = $incomingIdempotencyKey ?: Str::uuid()->toString();
        $overrideCooldown = $request->boolean('override_cooldown', false);

        // Capture logging information
        $userAgent = $request->userAgent();
        $ipAddress = $request->ip();

        // STEP 1: Lookup loyalty account by public_token (no store filter for auto-detection)
        $account = LoyaltyAccount::where('public_token', $token)
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

        // STEP 3: Determine if store was switched (for response metadata)
        $storeSwitched = $requestedStoreId && $requestedStoreId != $actualStoreId;

        // STEP 4a: Idempotency pre-check (before any cooldown logic)
        if ($incomingIdempotencyKey) {
            $existingEvent = \App\Models\StampEvent::where('idempotency_key', $incomingIdempotencyKey)->first();
            if ($existingEvent) {
                return response()->json([
                    'status' => 'duplicate',
                    'success' => false,
                    'message' => 'Already processed',
                    'store_switched' => false,
                ], 200);
            }
        }

        // STEP 4b: Hard server-side duplicate window (5s), regardless of override_cooldown
        $secondsSinceLastStamp = $account->last_stamped_at
            ? $account->last_stamped_at->diffInSeconds(now())
            : null;

        if ($secondsSinceLastStamp !== null && $secondsSinceLastStamp < 5) {
            return response()->json([
                'status' => 'duplicate',
                'success' => false,
                'message' => 'Duplicate scan ignored',
            ], 200);
        }

        // STEP 4c: UX cooldown (30s) - can be overridden
        if ($secondsSinceLastStamp !== null && $secondsSinceLastStamp < 30) {
            if (!$overrideCooldown) {
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
                ], 409);
            }
            // override_cooldown = true: allow if past 5-second hard window
        }

        // STEP 5: Call the service to perform the actual stamping
        // The service handles: authorization, idempotency, DB transaction, locking, audit logs, wallet job
        try {
            $result = $stampService->stamp(
                account: $account,
                staff: Auth::user(),
                count: $count,
                idempotencyKey: $idempotencyKey,
                userAgent: $userAgent,
                ipAddress: $ipAddress
            );
        } catch (ValidationException $e) {
            // Re-throw validation exceptions (e.g., access denied)
            throw $e;
        } catch (\Exception $e) {
            // Log unexpected errors for debugging
            \Log::error('Unexpected error in stamp service', [
                'error' => $e->getMessage(),
                'class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'account_id' => $account->id,
                'user_id' => Auth::id(),
            ]);
            // Re-throw to return proper error response
            throw $e;
        }

        // STEP 6: Handle duplicate/idempotent response
        if ($result->isDuplicate) {
            $account->refresh();
            $account->load(['store', 'customer']);
            $store = $account->store;
            
            return response()->json([
                'status' => 'duplicate',
                'success' => false,
                'storeName' => $store->name,
                'store_id_used' => $store->id,
                'store_name_used' => $store->name,
                'store_switched' => $storeSwitched,
                'customerLabel' => $account->customer->name ?? 'Customer',
                'stampCount' => $result->stampCount,
                'rewardBalance' => $result->rewardBalance,
                'rewardTarget' => $result->rewardTarget,
                'rewardAvailable' => $result->rewardBalance > 0,
                'message' => 'Already processed',
            ], 200);
        }

        // STEP 7: Format success response
        try {
            $account->refresh();
            $account->load(['store', 'customer']);
            $store = $account->store;

            // Get transaction for receipt (if points_transactions table exists)
            $transaction = null;
            try {
                if (\Schema::hasTable('points_transactions')) {
                    $transaction = PointsTransaction::where('idempotency_key', $idempotencyKey)->first();
                }
            } catch (\Exception $e) {
                // Log but don't fail if transaction lookup fails
                \Log::warning('Failed to lookup transaction for receipt', [
                    'idempotency_key' => $idempotencyKey,
                    'error' => $e->getMessage(),
                ]);
            }

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
            'stampCount' => $result->stampCount,
            'rewardBalance' => $result->rewardBalance,
            'rewardTarget' => $result->rewardTarget,
            'rewardAvailable' => $result->rewardBalance > 0,
            'rewardEarned' => $result->rewardEarned,
            'stampsRemaining' => max(0, $result->rewardTarget - $result->stampCount),
            'receipt' => [
                'transaction_id' => $transaction->id ?? null,
                'timestamp' => now()->toIso8601String(),
                'stamps_added' => $count,
                'new_total' => $result->stampCount,
            ],
        ]);
        } catch (\Exception $e) {
            // Log error but still return success if stamp actually happened
            \Log::error('Error formatting stamp response', [
                'error' => $e->getMessage(),
                'account_id' => $account->id ?? null,
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Return minimal success response
            return response()->json([
                'status' => 'success',
                'success' => true,
                'message' => 'Stamp processed successfully',
                'stampCount' => $result->stampCount ?? $account->stamp_count ?? 0,
                'rewardBalance' => $result->rewardBalance ?? $account->reward_balance ?? 0,
            ]);
        }
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
        // Strip "LR:" or "REDEEM:" prefix if present (support both for backward compatibility)
        if (Str::startsWith($token, 'LR:')) {
            $token = Str::substr($token, 3);
        } elseif (Str::startsWith($token, 'REDEEM:')) {
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
                // Log debug information to help diagnose the issue
                \Log::warning('Redeem token lookup failed', [
                    'token' => $token,
                    'token_length' => strlen($token),
                    'store_id' => $storeId,
                    'user_id' => Auth::id(),
                    'accounts_with_reward_balance' => LoyaltyAccount::where('store_id', $storeId)
                        ->where('reward_balance', '>', 0)
                        ->pluck('id', 'redeem_token')
                        ->toArray(),
                ]);
                
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
            
            // Rotate redeem_token after each redemption to prevent reuse
            // This ensures old QR codes cannot be scanned again
            if ($account->reward_balance > 0) {
                // Still have rewards, rotate token for security
                $account->redeem_token = Str::random(40);
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

            // Dispatch real-time event (wrap in try-catch to prevent errors from breaking the response)
            try {
                \Log::info('Dispatching StampUpdated event (redeem)', [
                    'public_token' => $account->public_token,
                    'channel' => 'loyalty-card.' . $account->public_token,
                    'stamp_count' => $account->stamp_count
                ]);
                
                StampUpdated::dispatch($account);
            } catch (\Exception $e) {
                // Log but don't fail the request if event dispatch fails
                \Log::error('Failed to dispatch StampUpdated event (redeem)', [
                    'public_token' => $account->public_token,
                    'error' => $e->getMessage(),
                ]);
            }
            
            // Dispatch wallet update job AFTER transaction commits
            // This ensures the job runs with the committed data (matching stamping service pattern)
            // Wrap in try-catch to prevent errors from breaking the response
            try {
                DB::afterCommit(function () use ($account) {
                    try {
                        \App\Jobs\UpdateWalletPassJob::dispatch($account->id)
                            ->onQueue('default');
                    } catch (\Exception $e) {
                        \Log::error('Failed to dispatch UpdateWalletPassJob (redeem)', [
                            'loyalty_account_id' => $account->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                });
            } catch (\Exception $e) {
                // Log but don't fail the request if afterCommit callback registration fails
                \Log::error('Failed to register afterCommit callback (redeem)', [
                    'loyalty_account_id' => $account->id,
                    'error' => $e->getMessage(),
                ]);
            }

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
            $transaction = null;
            try {
                $transaction = PointsTransaction::where('idempotency_key', $idempotencyKey)->first();
            } catch (\Exception $e) {
                // Log but don't fail if transaction lookup fails
                \Log::warning('Failed to lookup transaction for receipt (redeem)', [
                    'idempotency_key' => $idempotencyKey,
                    'error' => $e->getMessage(),
                ]);
            }
            
            $message = $quantity > 1 
                ? "Successfully redeemed {$quantity} rewards! Enjoy your {$store->reward_title}!"
                : "Reward redeemed successfully! Enjoy your {$store->reward_title}!";
            
            try {
                \Log::info('Redeem processed', [
                    'loyalty_account_id' => $account->id,
                    'store_id' => $store->id,
                    'user_id' => Auth::id(),
                    'quantity' => $quantity,
                    'reward_balance_after' => $account->reward_balance ?? 0,
                    'stamp_count' => $account->stamp_count,
                ]);

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
            } catch (\Exception $e) {
                // Log error but still return success if redeem actually happened
                \Log::error('Error formatting redeem response', [
                    'error' => $e->getMessage(),
                    'account_id' => $account->id ?? null,
                    'trace' => $e->getTraceAsString(),
                ]);
                
                // Return minimal success response
                return response()->json([
                    'success' => true,
                    'message' => 'Reward redeemed successfully',
                    'remaining_rewards' => $account->reward_balance ?? 0,
                ]);
            }
        });
    }
}
