<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\URL;

class MerchantVerifyEmail extends VerifyEmail implements ShouldQueue
{
    use Queueable;

    public $tries = 3;

    public $backoff = [30, 60, 120];

    public function __construct(public string $email)
    {
        $this->onQueue('emails');
        $this->afterCommit();
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        return $notifiable->email === $this->email && ! $notifiable->hasVerifiedEmail();
    }

    protected function verificationUrl($notifiable)
    {
        // Bind the email at dispatch time so delayed jobs never verify a replacement address.
        return rtrim(config('app.url'), '/').URL::temporarySignedRoute(
            'verification.verify', now()->addMinutes(config('auth.verification.expire', 60)),
            ['id' => $notifiable->getKey(), 'hash' => sha1($this->email)], absolute: false
        );
    }
}
