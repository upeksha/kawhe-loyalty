<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Password;

class MerchantResetPassword extends ResetPassword implements ShouldQueue
{
    use Queueable;

    public $tries = 3;

    public $backoff = [30, 60, 120];

    public function __construct(string $token, public string $email)
    {
        parent::__construct($token);
        $this->onQueue('emails');
        $this->afterCommit();
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        return $notifiable->getEmailForPasswordReset() === $this->email
            && Password::broker()->tokenExists($notifiable, $this->token);
    }

    protected function resetUrl($notifiable)
    {
        return rtrim(config('app.url'), '/').route('password.reset', [
            'token' => $this->token, 'email' => $this->email,
        ], false);
    }
}
