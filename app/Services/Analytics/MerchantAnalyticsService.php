<?php

namespace App\Services\Analytics;

use App\Models\LoyaltyAccount;
use App\Models\PointsTransaction;
use App\Models\StampEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class MerchantAnalyticsService
{
    public function getDashboardAnalytics(User $user, int $windowDays = 30, int $trendDays = 14): array
    {
        $storeIds = $user->stores()->pluck('id');

        if ($storeIds->isEmpty()) {
            return [
                'active_customers' => 0,
                'joins_last_window' => 0,
                'rewards_earned_last_window' => 0,
                'rewards_redeemed_last_window' => 0,
                'recent_activity_trend' => collect(),
            ];
        }

        $windowStart = now()->subDays($windowDays);
        $trendStart = now()->subDays($trendDays - 1)->startOfDay();

        $activeCustomers = LoyaltyAccount::query()
            ->whereIn('store_id', $storeIds)
            ->where(function ($query) use ($windowStart) {
                $query->where('created_at', '>=', $windowStart)
                    ->orWhere('last_stamped_at', '>=', $windowStart)
                    ->orWhere('reward_available_at', '>=', $windowStart)
                    ->orWhere('reward_redeemed_at', '>=', $windowStart);
            })
            ->count();

        $joinsLastWindow = LoyaltyAccount::query()
            ->whereIn('store_id', $storeIds)
            ->where('created_at', '>=', $windowStart)
            ->count();

        $rewardTransactions = PointsTransaction::query()
            ->whereIn('store_id', $storeIds)
            ->whereIn('type', ['earn', 'redeem'])
            ->where('created_at', '>=', $windowStart)
            ->get(['type', 'points', 'metadata']);

        $rewardsEarnedLastWindow = (int) $rewardTransactions
            ->where('type', 'earn')
            ->sum(fn (PointsTransaction $transaction) => (int) data_get($transaction->metadata, 'newly_earned_rewards', 0));

        $rewardsRedeemedLastWindow = (int) $rewardTransactions
            ->where('type', 'redeem')
            ->sum(fn (PointsTransaction $transaction) => (int) data_get($transaction->metadata, 'rewards_redeemed', abs((int) $transaction->points)));

        $trendDaysMap = $this->emptyTrendMap($trendStart, $trendDays);

        $joinsByDay = LoyaltyAccount::query()
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->whereIn('store_id', $storeIds)
            ->where('created_at', '>=', $trendStart)
            ->groupBy('day')
            ->pluck('total', 'day');

        $stampsByDay = StampEvent::query()
            ->selectRaw('DATE(created_at) as day, COALESCE(SUM(count), 0) as total')
            ->whereIn('store_id', $storeIds)
            ->where('type', 'stamp')
            ->where('created_at', '>=', $trendStart)
            ->groupBy('day')
            ->pluck('total', 'day');

        $redeemsByDay = StampEvent::query()
            ->selectRaw('DATE(created_at) as day, COALESCE(SUM(count), 0) as total')
            ->whereIn('store_id', $storeIds)
            ->where('type', 'redeem')
            ->where('created_at', '>=', $trendStart)
            ->groupBy('day')
            ->pluck('total', 'day');

        $trend = $trendDaysMap->map(function (array $row, string $day) use ($joinsByDay, $stampsByDay, $redeemsByDay) {
            $joins = (int) ($joinsByDay[$day] ?? 0);
            $stamps = (int) ($stampsByDay[$day] ?? 0);
            $redeems = (int) ($redeemsByDay[$day] ?? 0);

            return [
                'day' => $day,
                'label' => Carbon::parse($day)->format('M j'),
                'joins' => $joins,
                'stamps' => $stamps,
                'redeems' => $redeems,
                'total' => $joins + $stamps + $redeems,
            ];
        })->values();

        return [
            'active_customers' => $activeCustomers,
            'joins_last_window' => $joinsLastWindow,
            'rewards_earned_last_window' => $rewardsEarnedLastWindow,
            'rewards_redeemed_last_window' => $rewardsRedeemedLastWindow,
            'recent_activity_trend' => $trend,
        ];
    }

    protected function emptyTrendMap(Carbon $start, int $days): Collection
    {
        return collect(range(0, $days - 1))
            ->mapWithKeys(function (int $offset) use ($start) {
                $day = $start->copy()->addDays($offset)->toDateString();

                return [$day => ['day' => $day]];
            });
    }
}
