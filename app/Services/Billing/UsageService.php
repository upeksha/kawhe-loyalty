<?php

namespace App\Services\Billing;

use App\Models\User;
use App\Models\LoyaltyAccount;
use App\Models\Store;

class UsageService
{
    /**
     * Get the free plan limit for loyalty cards.
     *
     * @return int
     */
    public function freeLimit(): int
    {
        return 50;
    }

    /**
     * Count total loyalty cards across all stores owned by a merchant.
     * If subscription is cancelled, only counts cards created AFTER cancellation (non-grandfathered).
     *
     * @param User $user
     * @param bool $includeGrandfathered If true, counts all cards. If false, excludes grandfathered cards.
     * @return int
     */
    public function cardsCountForUser(User $user, bool $includeGrandfathered = true): int
    {
        try {
            // Get all store IDs owned by this user
            $storeIds = $user->stores()->pluck('id');

            if ($storeIds->isEmpty()) {
                return 0;
            }

            $query = LoyaltyAccount::whereIn('store_id', $storeIds);

            // If not including grandfathered, exclude cards created before subscription cancellation
            if (!$includeGrandfathered) {
                try {
                    $subscription = $user->subscription('default');
                    
                    // If subscription exists and has an ends_at date (cancelled), exclude cards created before that
                    if ($subscription && $subscription->ends_at) {
                        $query->where('created_at', '>=', $subscription->ends_at);
                    }
                    // If no subscription or no ends_at, all cards count (no grandfathering)
                } catch (\Exception $e) {
                    // If subscription check fails, count all cards (no grandfathering)
                    \Log::warning('Error checking subscription for grandfathering', [
                        'user_id' => $user->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return $query->count();
        } catch (\Exception $e) {
            \Log::error('Error counting cards for user', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            // Return 0 on error to be safe
            return 0;
        }
    }

    /**
     * Count grandfathered cards (cards created before subscription cancellation).
     *
     * @param User $user
     * @return int
     */
    public function grandfatheredCardsCount(User $user): int
    {
        try {
            $subscription = $user->subscription('default');
            
            // Only grandfathered if subscription exists and was cancelled (has ends_at)
            if (!$subscription || !$subscription->ends_at) {
                return 0;
            }

            $storeIds = $user->stores()->pluck('id');
            if ($storeIds->isEmpty()) {
                return 0;
            }

            // Count cards created BEFORE subscription cancellation
            return LoyaltyAccount::whereIn('store_id', $storeIds)
                ->where('created_at', '<', $subscription->ends_at)
                ->count();
        } catch (\Exception $e) {
            \Log::warning('Error counting grandfathered cards', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * Check if user has an active subscription.
     *
     * @param User $user
     * @return bool
     */
    public function isSubscribed(User $user): bool
    {
        try {
            // Check if user has a Stripe ID first
            if (!$user->hasStripeId()) {
                return false;
            }
            
            // Check for active subscription
            $subscription = $user->subscription('default');
            
            if (!$subscription) {
                return false;
            }
            
            // Check if subscription is active (not cancelled, not past due, etc.)
            return in_array($subscription->stripe_status, [
                'active',
                'trialing',
            ]);
        } catch (\Exception $e) {
            // If there's any error checking subscription, assume not subscribed
            \Log::warning('Error checking subscription status', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Check if user can create a new loyalty card.
     * Implements grandfathering: cards created before subscription cancellation remain active.
     *
     * @param User $user
     * @return bool
     */
    public function canCreateCard(User $user): bool
    {
        // Subscribed users can create unlimited cards
        if ($this->isSubscribed($user)) {
            return true;
        }

        // For non-subscribed users, count only non-grandfathered cards
        // (cards created after subscription cancellation)
        $nonGrandfatheredCount = $this->cardsCountForUser($user, includeGrandfathered: false);
        
        return $nonGrandfatheredCount < $this->freeLimit();
    }

    /**
     * Get usage statistics for a user.
     *
     * @param User $user
     * @return array
     */
    public function getUsageStats(User $user): array
    {
        $totalCardsCount = $this->cardsCountForUser($user, includeGrandfathered: true);
        $nonGrandfatheredCount = $this->cardsCountForUser($user, includeGrandfathered: false);
        $grandfatheredCount = $this->grandfatheredCardsCount($user);
        $limit = $this->freeLimit();
        $isSubscribed = $this->isSubscribed($user);
        $subscription = $user->subscription('default');
        $hasCancelledSubscription = $subscription && $subscription->ends_at && !$isSubscribed;

        return [
            'cards_count' => $totalCardsCount,
            'non_grandfathered_count' => $nonGrandfatheredCount,
            'grandfathered_count' => $grandfatheredCount,
            'limit' => $limit,
            'is_subscribed' => $isSubscribed,
            'has_cancelled_subscription' => $hasCancelledSubscription,
            'can_create_card' => $this->canCreateCard($user),
            'usage_percentage' => $isSubscribed ? 0 : min(100, ($nonGrandfatheredCount / $limit) * 100),
        ];
    }
}
