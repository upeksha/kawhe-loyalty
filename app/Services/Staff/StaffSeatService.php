<?php

namespace App\Services\Staff;

use App\Models\Store;
use App\Models\StoreStaff;
use App\Models\StoreStaffInvitation;
use App\Services\Billing\UsageService;
use Illuminate\Support\Str;

class StaffSeatService
{
    public function limit(Store $store): ?int
    {
        return app(UsageService::class)->staffPerStoreLimitForUser($store->user);
    }

    public function activeCount(Store $store): int
    {
        return StoreStaff::where('store_id', $store->id)->count();
    }

    public function occupiedCount(Store $store, ?string $replacingEmail = null): int
    {
        $pending = StoreStaffInvitation::where('store_id', $store->id)
            ->where('expires_at', '>', now());

        if ($replacingEmail !== null) {
            $pending->where('email', '!=', Str::lower($replacingEmail));
        }

        return $this->activeCount($store) + $pending->count();
    }

    public function canInvite(Store $store, string $email): bool
    {
        $limit = $this->limit($store);

        return $limit === null || $this->occupiedCount($store, $email) < $limit;
    }

    public function canAccept(Store $store, ?int $userId = null): bool
    {
        $limit = $this->limit($store);

        return $limit === null
            || ($userId && StoreStaff::where('store_id', $store->id)->where('user_id', $userId)->exists())
            || $this->activeCount($store) < $limit;
    }
}
