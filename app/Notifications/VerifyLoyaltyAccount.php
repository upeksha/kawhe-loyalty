<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerifyLoyaltyAccount extends Notification
{
    use Queueable;

    protected $account;

    /**
     * Create a new notification instance.
     */
    public function __construct($account)
    {
        $this->account = $account;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $url = \Illuminate\Support\Facades\URL::signedRoute('card.verification.verify', [
            'public_token' => $this->account->public_token,
            'id' => $this->account->id,
            'hash' => sha1($this->account->customer->email),
        ]);

        return (new MailMessage)
            ->subject('Verify your Kawhe Loyalty Card')
            ->line('Click the button below to verify your email address and protect your loyalty card.')
            ->action('Verify Email', $url)
            ->line('Verification allows you to recover your card by email and redeem rewards.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
