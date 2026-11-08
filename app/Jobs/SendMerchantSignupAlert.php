<?php

namespace App\Jobs;

use App\Mail\MerchantSignupAlertEmail;
use App\Models\MerchantSignupAlert;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class SendMerchantSignupAlert implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $uniqueFor = 3600;

    public array $backoff = [60, 300, 900];

    public function __construct(public int $alertId)
    {
        $this->onQueue('emails');
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return 'merchant-signup-'.$this->alertId;
    }

    public function handle(): void
    {
        $recipient = config('admin.signup_alert_email');
        if (! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        // Commit the attempt even on mail failure. The scheduler can recover a
        // lost job without retrying a permanently failing message indefinitely.
        $claimed = MerchantSignupAlert::whereKey($this->alertId)->whereNull('sent_at')
            ->where('attempts', '<', 3)->increment('attempts', 1, ['last_attempt_at' => now()]);
        if (! $claimed) {
            return;
        }
        DB::transaction(function () use ($recipient) {
            $alert = MerchantSignupAlert::lockForUpdate()->find($this->alertId);
            if (! $alert || $alert->sent_at || ! $alert->user || $alert->user->isSuperAdmin()) {
                return;
            }
            Mail::to($recipient)->send(new MerchantSignupAlertEmail($alert));
            $alert->update(['sent_at' => now()]);
        });
    }
}
