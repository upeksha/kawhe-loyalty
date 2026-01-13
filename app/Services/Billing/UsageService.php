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
     *
     * @param User $user
     * @return int
     */
    public function cardsCountForUser(User $user): int
    {
        // Get all store IDs owned by this user
        $storeIds = $user->stores()->pluck('id');

        if ($storeIds->isEmpty()) {
            return 0;
        }

        // Count loyalty accounts for all stores owned by this user
        return LoyaltyAccount::whereIn('store_id', $storeIds)->count();
    }

    /**
     * Check if user has an active subscription.
     *
     * @param User $user
     * @return bool
     */
    public function isSubscribed(User $user): bool
    {
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
    }

    /**
     * Check if user can create a new loyalty card.
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

        // Free users limited to 50 cards
        return $this->cardsCountForUser($user) < $this->freeLimit();
    }

    /**
     * Get usage statistics for a user.
     *
     * @param User $user
     * @return array
     */
    public function getUsageStats(User $user): array
    {
        $cardsCount = $this->cardsCountForUser($user);
        $limit = $this->freeLimit();
        $isSubscribed = $this->isSubscribed($user);

        return [
            'cards_count' => $cardsCount,
            'limit' => $limit,
            'is_subscribed' => $isSubscribed,
            'can_create_card' => $this->canCreateCard($user),
            'usage_percentage' => $isSubscribed ? 0 : min(100, ($cardsCount / $limit) * 100),
        ];
    }
}
