<?php

namespace App\Services\Loyalty;

use Carbon\Carbon;

/**
 * Data Transfer Object for stamp operation results.
 */
class StampResultDTO
{
    public function __construct(
        public readonly int $stampCount,
        public readonly int $rewardBalance,
        public readonly int $rewardTarget,
        public readonly ?Carbon $lastStampedAt,
        public readonly bool $rewardEarned,
        public readonly bool $isDuplicate = false
    ) {
    }

    /**
     * Convert to array for JSON response.
     */
    public function toArray(): array
    {
        return [
            'stamp_count' => $this->stampCount,
            'reward_balance' => $this->rewardBalance,
            'reward_target' => $this->rewardTarget,
            'last_stamped_at' => $this->lastStampedAt?->toIso8601String(),
            'reward_earned' => $this->rewardEarned,
            'is_duplicate' => $this->isDuplicate,
        ];
    }
}
