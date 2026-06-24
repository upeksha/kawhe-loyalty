<?php

namespace App\Services\Billing;

use App\Models\LoyaltyProgram;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class UsageService
{
    public function freeLimit(): int
    {
        return 1;
    }

    public function paidLimit(): int
    {
        return 3;
    }

    public function programLimitForUser(User $user): int
    {
        return $this->isSubscribed($user) ? $this->paidLimit() : $this->freeLimit();
    }

    public function programsCountForUser(User $user, bool $includeGrandfathered = true): int
    {
        try {
            $storeIds = $user->stores()->pluck('id');

            if ($storeIds->isEmpty()) {
                return 0;
            }

            $query = LoyaltyProgram::query()
                ->whereIn('store_id', $storeIds)
                ->whereNull('deleted_at');

            if (! $includeGrandfathered) {
                try {
                    if (Schema::hasTable('subscriptions') && Schema::hasColumn('users', 'stripe_id')) {
                        $subscription = $user->subscription('default');

                        if ($subscription && $subscription->ends_at) {
                            $query->where('created_at', '>=', $subscription->ends_at);
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('Error checking subscription for program grandfathering', [
                        'user_id' => $user->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return $query->count();
        } catch (\Exception $e) {
            Log::error('Error counting loyalty programs for user', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    public function grandfatheredProgramsCount(User $user): int
    {
        try {
            if (! Schema::hasTable('subscriptions') || ! Schema::hasColumn('users', 'stripe_id')) {
                return 0;
            }

            $subscription = $user->subscription('default');

            if (! $subscription || ! $subscription->ends_at) {
                return 0;
            }

            $storeIds = $user->stores()->pluck('id');
            if ($storeIds->isEmpty()) {
                return 0;
            }

            return LoyaltyProgram::query()
                ->whereIn('store_id', $storeIds)
                ->whereNull('deleted_at')
                ->where('created_at', '<', $subscription->ends_at)
                ->count();
        } catch (\Exception $e) {
            Log::warning('Error counting grandfathered loyalty programs', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    public function isSubscribed(User $user): bool
    {
        try {
            try {
                if (! Schema::hasColumn('users', 'stripe_id')) {
                    Log::warning('Cashier migrations not run - stripe_id column missing');

                    return false;
                }
            } catch (\Exception $e) {
                Log::warning('Error checking for stripe_id column', ['error' => $e->getMessage()]);

                return false;
            }

            try {
                if (! $user->hasStripeId()) {
                    return false;
                }
            } catch (\Exception $e) {
                Log::warning('Error checking hasStripeId', ['error' => $e->getMessage()]);

                return false;
            }

            try {
                if (! Schema::hasTable('subscriptions')) {
                    Log::warning('Cashier migrations not run - subscriptions table missing');

                    return false;
                }
            } catch (\Exception $e) {
                Log::warning('Error checking for subscriptions table', ['error' => $e->getMessage()]);

                return false;
            }

            $subscription = $user->subscription('default');

            if (! $subscription) {
                return false;
            }

            return in_array($subscription->stripe_status, [
                'active',
                'trialing',
            ], true);
        } catch (\Exception $e) {
            Log::warning('Error checking subscription status', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return false;
        }
    }

    public function canCreateProgram(User $user): bool
    {
        $limit = $this->programLimitForUser($user);
        $nonGrandfatheredCount = $this->programsCountForUser($user, includeGrandfathered: false);

        return $nonGrandfatheredCount < $limit;
    }

    public function canCreateCard(User $user): bool
    {
        return $this->canCreateProgram($user);
    }

    public function getUsageStats(User $user): array
    {
        $programsCount = $this->programsCountForUser($user, includeGrandfathered: true);
        $nonGrandfatheredCount = $this->programsCountForUser($user, includeGrandfathered: false);
        $grandfatheredCount = $this->grandfatheredProgramsCount($user);
        $isSubscribed = $this->isSubscribed($user);
        $subscription = $user->subscription('default');
        $hasCancelledSubscription = $subscription && $subscription->ends_at && ! $isSubscribed;
        $limit = $this->programLimitForUser($user);

        return [
            'programs_count' => $programsCount,
            'cards_count' => $programsCount,
            'non_grandfathered_programs_count' => $nonGrandfatheredCount,
            'non_grandfathered_count' => $nonGrandfatheredCount,
            'grandfathered_programs_count' => $grandfatheredCount,
            'grandfathered_count' => $grandfatheredCount,
            'limit' => $limit,
            'free_limit' => $this->freeLimit(),
            'paid_limit' => $this->paidLimit(),
            'is_subscribed' => $isSubscribed,
            'has_cancelled_subscription' => $hasCancelledSubscription,
            'can_create_program' => $this->canCreateProgram($user),
            'can_create_card' => $this->canCreateProgram($user),
            'usage_percentage' => $limit > 0 ? min(100, ($nonGrandfatheredCount / $limit) * 100) : 0,
        ];
    }
}
