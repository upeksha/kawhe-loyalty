<?php

namespace App\Services;

use App\Jobs\SendMerchantSignupAlert;
use App\Models\MerchantSignupAlert;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MerchantSignupAlerts
{
    public function record(User $user, string $event): void
    {
        if (! in_array($event, ['registered', 'verified'], true)) {
            throw new \InvalidArgumentException('Unknown signup event.');
        }
        if ($user->isSuperAdmin() || $user->is_staff_account || ! $user->stores()->exists()) {
            return;
        }
        $alert = MerchantSignupAlert::firstOrCreate(['user_id' => $user->id, 'event' => $event]);
        DB::afterCommit(function () use ($alert) {
            $this->dispatch($alert);
        });
    }

    public function dispatch(MerchantSignupAlert $alert): void
    {
        if (! config('admin.signup_alert_email') || $alert->sent_at || $alert->attempts >= 3) {
            return;
        }
        try {
            SendMerchantSignupAlert::dispatch($alert->id);
        } catch (\Throwable $e) {
            // The durable outbox is retried by the scheduler; registration must
            // still succeed when the email queue is temporarily unavailable.
            Log::warning('Signup alert dispatch deferred', ['alert_id' => $alert->id]);
        }
    }
}
