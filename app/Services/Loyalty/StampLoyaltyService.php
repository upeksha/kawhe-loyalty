<?php

namespace App\Services\Loyalty;

use App\Events\StampUpdated;
use App\Jobs\UpdateWalletPassJob;
use App\Models\LoyaltyAccount;
use App\Models\PointsTransaction;
use App\Models\StampEvent;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class StampLoyaltyService
{
    /**
     * Stamp a loyalty account safely with full audit trail and wallet update.
     *
     * @param LoyaltyAccount $account The loyalty account to stamp
     * @param User $staff The merchant/staff user performing the stamp
     * @param int $count Number of stamps to add (default 1)
     * @param string|null $idempotencyKey Optional idempotency key (auto-generated if null)
     * @param string|null $userAgent Optional user agent for audit
     * @param string|null $ipAddress Optional IP address for audit
     * @return StampResultDTO
     * @throws ValidationException
     */
    public function stamp(
        LoyaltyAccount $account,
        User $staff,
        int $count = 1,
        ?string $idempotencyKey = null,
        ?string $userAgent = null,
        ?string $ipAddress = null
    ): StampResultDTO {
        // Generate idempotency key if not provided
        if (!$idempotencyKey) {
            $idempotencyKey = \Illuminate\Support\Str::uuid()->toString();
        }

        // Validate staff has access to the store
        $this->validateStaffAccess($account, $staff);

        // Get store and reward target
        $store = $account->store;
        $rewardTarget = $store->reward_target ?? config('loyalty.reward_target', 10);

        // Perform stamping in transaction
        return DB::transaction(function () use (
            $account,
            $staff,
            $store,
            $rewardTarget,
            $count,
            $idempotencyKey,
            $userAgent,
            $ipAddress
        ) {
            // Check idempotency first (before locking)
            $existingEvent = StampEvent::where('idempotency_key', $idempotencyKey)->first();
            if ($existingEvent) {
                // Return existing result without modifying
                $existingAccount = $existingEvent->loyaltyAccount;
                $existingAccount->load(['store', 'customer']);
                
                return new StampResultDTO(
                    stampCount: $existingAccount->stamp_count,
                    rewardBalance: $existingAccount->reward_balance ?? 0,
                    rewardTarget: $existingAccount->store->reward_target ?? $rewardTarget,
                    lastStampedAt: $existingAccount->last_stamped_at,
                    rewardEarned: false,
                    isDuplicate: true
                );
            }

            // Lock the account row for update (prevents concurrent modifications)
            $account = LoyaltyAccount::whereKey($account->id)
                ->lockForUpdate()
                ->with(['store', 'customer'])
                ->firstOrFail();

            // Store original values for audit
            $stampCountBefore = $account->stamp_count;
            $rewardBalanceBefore = $account->reward_balance ?? 0;
            $versionBefore = $account->version ?? 0;

            // Increment stamp count
            $account->stamp_count += $count;
            $account->last_stamped_at = now();
            $account->version = ($account->version ?? 0) + 1;

            // Calculate rewards earned (handle overshoot)
            $newlyEarned = 0;
            while ($account->stamp_count >= $rewardTarget) {
                $account->stamp_count -= $rewardTarget;
                $account->reward_balance = ($account->reward_balance ?? 0) + 1;
                $newlyEarned++;
            }

            // Update reward_available_at and redeem_token based on reward_balance
            if ($account->reward_balance > 0) {
                // Ensure reward_available_at is set when rewards become available
                if (is_null($account->reward_available_at)) {
                    $account->reward_available_at = now();
                }
                // Ensure redeem_token exists
                if (is_null($account->redeem_token)) {
                    $account->redeem_token = \Illuminate\Support\Str::random(\App\Models\LoyaltyAccount::REDEEM_TOKEN_LENGTH);
                }
            } else {
                // No rewards available
                $account->reward_available_at = null;
                $account->redeem_token = null;
            }

            // Save account
            $account->save();

            // Create audit logs
            $rewardEarned = $newlyEarned > 0;

            // Create stamp event
            try {
                $stampEvent = StampEvent::create([
                    'loyalty_account_id' => $account->id,
                    'store_id' => $store->id,
                    'user_id' => $staff->id,
                    'type' => 'stamp', // Use lowercase to match existing convention
                    'count' => $count,
                    'idempotency_key' => $idempotencyKey,
                    'user_agent' => $userAgent,
                    'ip_address' => $ipAddress,
                ]);
            } catch (\Illuminate\Database\QueryException $e) {
                // Handle unique constraint violation (duplicate idempotency key)
                // Check if it's a duplicate entry error
                if ($e->getCode() === '23000' || str_contains($e->getMessage(), 'Duplicate entry') || str_contains($e->getMessage(), 'UNIQUE constraint')) {
                    // Rollback this transaction
                    DB::rollBack();
                    
                    // Another request processed this key - return existing result
                    $existingEvent = StampEvent::where('idempotency_key', $idempotencyKey)->first();
                    if ($existingEvent) {
                        $existingAccount = $existingEvent->loyaltyAccount;
                        $existingAccount->load(['store', 'customer']);
                        
                        return new StampResultDTO(
                            stampCount: $existingAccount->stamp_count,
                            rewardBalance: $existingAccount->reward_balance ?? 0,
                            rewardTarget: $existingAccount->store->reward_target ?? $rewardTarget,
                            lastStampedAt: $existingAccount->last_stamped_at,
                            rewardEarned: false,
                            isDuplicate: true
                        );
                    }
                }
                throw $e;
            }

            // Create points transaction (if table exists)
            if (Schema::hasTable('points_transactions')) {
                try {
                    PointsTransaction::create([
                        'loyalty_account_id' => $account->id,
                        'store_id' => $store->id,
                        'user_id' => $staff->id,
                        'type' => 'earn',
                        'points' => $count,
                        'idempotency_key' => $idempotencyKey,
                        'metadata' => [
                            'stamp_count_before' => $stampCountBefore,
                            'stamp_count_after' => $account->stamp_count,
                            'reward_balance_before' => $rewardBalanceBefore,
                            'reward_balance_after' => $account->reward_balance ?? 0,
                            'newly_earned_rewards' => $newlyEarned,
                            'version_before' => $versionBefore,
                            'version_after' => $account->version,
                        ],
                        'user_agent' => $userAgent,
                        'ip_address' => $ipAddress,
                    ]);
                } catch (\Exception $e) {
                    // Log but don't fail if points_transactions table doesn't exist or has issues
                    Log::warning('Failed to create points transaction', [
                        'error' => $e->getMessage(),
                        'loyalty_account_id' => $account->id,
                    ]);
                }
            }

            // Refresh account to get latest state
            $account->refresh();
            $account->load(['store', 'customer']);

            // Log stamping outcome (for debugging intermittent issues)
            Log::info('Stamp applied', [
                'loyalty_account_id' => $account->id,
                'store_id' => $store->id,
                'staff_id' => $staff->id,
                'idempotency_key' => $idempotencyKey,
                'stamp_count_before' => $stampCountBefore,
                'stamp_count_after' => $account->stamp_count,
                'reward_balance_before' => $rewardBalanceBefore,
                'reward_balance_after' => $account->reward_balance ?? 0,
                'reward_earned' => $rewardEarned,
                'newly_earned_rewards' => $newlyEarned,
            ]);

            // Dispatch wallet update job AFTER transaction commits
            // This ensures the job runs with the committed data
            // Wrap in try-catch to prevent errors from breaking the response
            try {
                DB::afterCommit(function () use ($account) {
                    try {
                        UpdateWalletPassJob::dispatch($account->id)
                            ->onQueue('default');
                    } catch (\Exception $e) {
                        // Log but don't fail if job dispatch fails
                        Log::error('Failed to dispatch UpdateWalletPassJob (stamp)', [
                            'loyalty_account_id' => $account->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                });
            } catch (\Exception $e) {
                // Log but don't fail if afterCommit callback registration fails
                Log::error('Failed to register afterCommit callback (stamp)', [
                    'loyalty_account_id' => $account->id,
                    'error' => $e->getMessage(),
                ]);
            }

            // Dispatch real-time event (wrap in try-catch to prevent errors from breaking the response)
            try {
                StampUpdated::dispatch($account);
            } catch (\Exception $e) {
                // Log but don't fail if event dispatch fails
                Log::error('Failed to dispatch StampUpdated event (stamp)', [
                    'loyalty_account_id' => $account->id,
                    'error' => $e->getMessage(),
                ]);
            }

            return new StampResultDTO(
                stampCount: $account->stamp_count,
                rewardBalance: $account->reward_balance ?? 0,
                rewardTarget: $rewardTarget,
                lastStampedAt: $account->last_stamped_at,
                rewardEarned: $rewardEarned,
                isDuplicate: false
            );
        });
    }

    /**
     * Validate that staff has access to the store that owns the loyalty account.
     *
     * @param LoyaltyAccount $account
     * @param User $staff
     * @return void
     * @throws ValidationException
     */
    protected function validateStaffAccess(LoyaltyAccount $account, User $staff): void
    {
        $account->load('store');
        $store = $account->store;

        if (!$store) {
            throw ValidationException::withMessages([
                'account' => 'Loyalty account has no associated store.'
            ]);
        }

        // Check if staff owns the store or is super admin
        $staffOwnsStore = $staff->stores()->where('id', $store->id)->exists();
        
        if (!$staffOwnsStore && !$staff->isSuperAdmin()) {
            throw ValidationException::withMessages([
                'token' => 'This loyalty card belongs to a store you do not have access to. Please contact support if you believe this is an error.'
            ]);
        }
    }
}
