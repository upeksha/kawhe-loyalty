<?php

namespace App\Console\Commands;

use App\Models\MerchantSignupAlert;
use App\Services\MerchantSignupAlerts;
use Illuminate\Console\Command;

class DispatchMerchantSignupAlerts extends Command
{
    protected $signature = 'admin:dispatch-signup-alerts {--retry-failed : Reset exhausted attempts before dispatching}';

    protected $description = 'Dispatch pending café signup alerts from the durable outbox';

    public function handle(MerchantSignupAlerts $alerts): int
    {
        if (! filter_var(config('admin.signup_alert_email'), FILTER_VALIDATE_EMAIL)) {
            $this->warn('Signup alert recipient is not configured.');

            return self::SUCCESS;
        }
        if ($this->option('retry-failed')) {
            MerchantSignupAlert::whereNull('sent_at')->where('attempts', '>=', 3)->update(['attempts' => 0]);
        }
        MerchantSignupAlert::whereNull('sent_at')->where('attempts', '<', 3)
            ->orderBy('id')->limit(50)->get()->each(fn ($alert) => $alerts->dispatch($alert));

        return self::SUCCESS;
    }
}
