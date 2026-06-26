<?php

namespace App\Services\Billing;

use App\Models\LoyaltyAccount;
use App\Models\LoyaltyProgram;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class UsageService
{
    public function planFor(User $user): string
    {
        if ($this->isSubscribed($user)) {
            return 'pro';
        }

        return 'free';
    }

    public function planConfig(string $plan): array
    {
        return config("billing.plans.{$plan}", config('billing.plans.free'));
    }

    public function planLabel(User $user): string
    {
        return (string) ($this->planConfig($this->planFor($user))['label'] ?? 'Free');
    }

    /** @deprecated Use storesLimitForUser() or programsPerStoreLimitForUser() */
    public function freeLimit(): int
    {
        return (int) config('billing.plans.free.programs_per_store', 1);
    }

    /** @deprecated Use storesLimitForUser() */
    public function paidLimit(): int
    {
        return (int) config('billing.plans.pro.stores', 3);
    }

    public function storesLimitForUser(User $user): ?int
    {
        $limit = $this->planConfig($this->planFor($user))['stores'] ?? null;

        return $limit === null ? null : (int) $limit;
    }

    public function programsPerStoreLimitForUser(User $user): ?int
    {
        $limit = $this->planConfig($this->planFor($user))['programs_per_store'] ?? null;

        return $limit === null ? null : (int) $limit;
    }

    public function customersPerProgramLimitForUser(User $user): ?int
    {
        $limit = $this->planConfig($this->planFor($user))['customers_per_program'] ?? null;

        return $limit === null ? null : (int) $limit;
    }

    public function storesCountForUser(User $user): int
    {
        try {
            return $user->stores()->whereNull('deleted_at')->count();
        } catch (\Exception $e) {
            Log::error('Error counting stores for user', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    public function programsCountForStore(Store $store): int
    {
        return $store->loyaltyPrograms()->whereNull('deleted_at')->count();
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

    public function customersCountForProgram(LoyaltyProgram $program): int
    {
        return LoyaltyAccount::query()
            ->where('loyalty_program_id', $program->id)
            ->count();
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

    public function canCreateStore(User $user): bool
    {
        $limit = $this->storesLimitForUser($user);

        if ($limit === null) {
            return true;
        }

        return $this->storesCountForUser($user) < $limit;
    }

    public function canCreateProgramForStore(User $user, Store $store): bool
    {
        if ((int) $store->user_id !== (int) $user->id) {
            return false;
        }

        $limit = $this->programsPerStoreLimitForUser($user);

        if ($limit === null) {
            return true;
        }

        return $this->programsCountForStore($store) < $limit;
    }

    public function canCreateProgram(User $user, ?Store $store = null): bool
    {
        if ($store !== null) {
            return $this->canCreateProgramForStore($user, $store);
        }

        if ($this->canCreateStore($user)) {
            return true;
        }

        foreach ($user->stores()->whereNull('deleted_at')->get() as $ownedStore) {
            if ($this->canCreateProgramForStore($user, $ownedStore)) {
                return true;
            }
        }

        return false;
    }

    public function canCreateCard(User $user, ?Store $store = null): bool
    {
        return $this->canCreateProgram($user, $store);
    }

    public function canAcceptNewCustomer(LoyaltyProgram $program): bool
    {
        $store = $program->store;
        $merchant = $store?->user;

        if (! $merchant) {
            return false;
        }

        $limit = $this->customersPerProgramLimitForUser($merchant);

        if ($limit === null) {
            return true;
        }

        return $this->customersCountForProgram($program) < $limit;
    }

    public function programLimitForUser(User $user): int
    {
        return $this->programsPerStoreLimitForUser($user) ?? PHP_INT_MAX;
    }

    public function getUsageStats(User $user): array
    {
        $plan = $this->planFor($user);
        $planConfig = $this->planConfig($plan);
        $programsCount = $this->programsCountForUser($user, includeGrandfathered: true);
        $nonGrandfatheredCount = $this->programsCountForUser($user, includeGrandfathered: false);
        $grandfatheredCount = $this->grandfatheredProgramsCount($user);
        $isSubscribed = $this->isSubscribed($user);
        $subscription = $user->subscription('default');
        $hasCancelledSubscription = $subscription && $subscription->ends_at && ! $isSubscribed;
        $storesCount = $this->storesCountForUser($user);
        $storesLimit = $this->storesLimitForUser($user);
        $programsPerStoreLimit = $this->programsPerStoreLimitForUser($user);
        $customersPerProgramLimit = $this->customersPerProgramLimitForUser($user);

        $primaryStore = $user->stores()->whereNull('deleted_at')->orderBy('id')->first();
        $primaryStoreProgramsCount = $primaryStore ? $this->programsCountForStore($primaryStore) : 0;
        $primaryStoreCanCreateProgram = $primaryStore
            ? $this->canCreateProgramForStore($user, $primaryStore)
            : $this->canCreateProgram($user);
        $primaryProgram = $primaryStore?->defaultLoyaltyProgram ?? $primaryStore?->loyaltyPrograms()->whereNull('deleted_at')->orderBy('id')->first();
        $primaryProgramCustomersCount = $primaryProgram ? $this->customersCountForProgram($primaryProgram) : 0;
        $canAcceptNewCustomer = $primaryProgram ? $this->canAcceptNewCustomer($primaryProgram) : true;

        $programsLimit = $programsPerStoreLimit ?? PHP_INT_MAX;
        $storesUsagePercent = $storesLimit !== null && $storesLimit > 0
            ? min(100, ($storesCount / $storesLimit) * 100)
            : 0;
        $programsUsagePercent = $programsPerStoreLimit !== null && $programsPerStoreLimit > 0 && $primaryStore
            ? min(100, ($primaryStoreProgramsCount / $programsPerStoreLimit) * 100)
            : 0;
        $customersUsagePercent = $customersPerProgramLimit !== null && $customersPerProgramLimit > 0 && $primaryProgram
            ? min(100, ($primaryProgramCustomersCount / $customersPerProgramLimit) * 100)
            : 0;

        $storesCardUsage = $user->stores()
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get()
            ->map(function (Store $store) use ($user) {
                $programsCount = $this->programsCountForStore($store);

                return [
                    'store_id' => $store->id,
                    'store_name' => $store->name,
                    'programs_count' => $programsCount,
                    'can_create_program' => $this->canCreateProgramForStore($user, $store),
                ];
            })
            ->values()
            ->all();

        return [
            'plan' => $plan,
            'plan_label' => $planConfig['label'] ?? ucfirst($plan),
            'stores_count' => $storesCount,
            'stores_limit' => $storesLimit,
            'can_create_store' => $this->canCreateStore($user),
            'programs_per_store_limit' => $programsPerStoreLimit,
            'customers_per_program_limit' => $customersPerProgramLimit,
            'primary_store_programs_count' => $primaryStoreProgramsCount,
            'primary_store_can_create_program' => $primaryStoreCanCreateProgram,
            'stores_card_usage' => $storesCardUsage,
            'primary_program_customers_count' => $primaryProgramCustomersCount,
            'programs_count' => $programsCount,
            'cards_count' => $programsCount,
            'non_grandfathered_programs_count' => $nonGrandfatheredCount,
            'non_grandfathered_count' => $nonGrandfatheredCount,
            'grandfathered_programs_count' => $grandfatheredCount,
            'grandfathered_count' => $grandfatheredCount,
            'limit' => $programsPerStoreLimit ?? $storesLimit ?? 0,
            'free_limit' => (int) config('billing.plans.free.programs_per_store', 1),
            'paid_limit' => (int) config('billing.plans.pro.stores', 3),
            'is_subscribed' => $isSubscribed,
            'has_cancelled_subscription' => $hasCancelledSubscription,
            'can_create_program' => $this->canCreateProgram($user),
            'can_create_card' => $this->canCreateProgram($user),
            'can_accept_new_customer' => $canAcceptNewCustomer,
            'usage_percentage' => max($storesUsagePercent, $programsUsagePercent, $customersUsagePercent),
            'stores_usage_percentage' => $storesUsagePercent,
            'programs_usage_percentage' => $programsUsagePercent,
            'customers_usage_percentage' => $customersUsagePercent,
        ];
    }
}
