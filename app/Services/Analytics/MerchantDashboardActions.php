<?php

namespace App\Services\Analytics;

use App\Models\LoyaltyAccount;
use App\Models\StampEvent;
use App\Models\User;
use App\Services\Staff\StaffSeatService;
use Illuminate\Support\Facades\DB;

class MerchantDashboardActions
{
    public function forOwner(User $owner, array $usage): array
    {
        $stores = $owner->stores()->orderBy('id')->get();
        $storeIds = $stores->pluck('id');
        $firstStore = $stores->first();
        $customerCards = LoyaltyAccount::whereIn('store_id', $storeIds)->count();
        $actions = [];

        if ($firstStore && $customerCards === 0) {
            $actions[] = [
                'title' => 'Display your join poster to get your first customer.',
                'detail' => 'Put the QR poster where customers can see it at the counter.',
                'label' => 'Open join poster',
                'url' => $firstStore->merchantPosterUrl(),
            ];
        }

        if ($customerCards > 0) {
            $nearlyReady = (int) DB::table('loyalty_accounts as accounts')
                ->join('stores', 'stores.id', '=', 'accounts.store_id')
                ->leftJoin('loyalty_programs as programs', 'programs.id', '=', 'accounts.loyalty_program_id')
                ->whereIn('accounts.store_id', $storeIds)
                ->where('accounts.stamp_count', '>', 0)
                ->whereRaw('accounts.stamp_count + 1 = COALESCE(programs.reward_target, stores.reward_target)')
                ->distinct('accounts.customer_id')
                ->count('accounts.customer_id');

            if ($nearlyReady > 0) {
                $actions[] = [
                    'title' => $nearlyReady.' '.($nearlyReady === 1 ? 'customer is' : 'customers are').' one stamp away.',
                    'detail' => 'See the cards close to their next reward.',
                    'label' => 'View customers',
                    'url' => route('merchant.customers.index', ['near_reward' => 1]),
                ];
            }

            $joinsThisWeek = LoyaltyAccount::whereIn('store_id', $storeIds)
                ->where('created_at', '>=', now()->subDays(7))
                ->exists();
            $stampsThisWeek = StampEvent::whereIn('store_id', $storeIds)
                ->where('created_at', '>=', now()->subDays(7))
                ->exists();

            if (! $joinsThisWeek && ! $stampsThisWeek && $firstStore) {
                $actions[] = [
                    'title' => 'No activity this week—share your join link.',
                    'detail' => 'Your join link and printable poster are ready to share.',
                    'label' => 'Get join link',
                    'url' => $firstStore->merchantPosterUrl(),
                ];
            }
        }

        $used = (int) ($usage['primary_program_customers_count'] ?? 0);
        $limit = $usage['customers_per_program_limit'] ?? null;
        if (! ($usage['is_subscribed'] ?? false) && $limit && $used / $limit >= 0.75) {
            $actions[] = [
                'title' => 'You are using '.number_format($used).' of '.number_format($limit).' Free-plan customer places.',
                'detail' => 'Check your plan before this card reaches its customer limit.',
                'label' => 'View plan',
                'url' => route('billing.index'),
            ];
        }

        if ($firstStore) {
            $seats = app(StaffSeatService::class);
            $staffLimit = $seats->limit($firstStore);
            if ($staffLimit === null || $seats->occupiedCount($firstStore) < $staffLimit) {
                $actions[] = [
                    'title' => 'Invite a staff member.',
                    'detail' => 'Give a team member their own login and choose what they can do.',
                    'label' => 'Open Team',
                    'url' => route('merchant.staff.index'),
                ];
            }
        }

        if ($customerCards > 0) {
            $actions[] = [
                'title' => 'Download this month’s customer activity.',
                'detail' => 'Get a CSV summary of new cards, stamps and redemptions across your stores.',
                'label' => 'Download activity CSV',
                'url' => route('merchant.activity.export'),
            ];
        }

        return $actions;
    }
}
