<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerifyLoyaltyAccount extends Notification implements ShouldQueue
{
    use Queueable;

    protected $account;

    public $tries = 3;

    public $backoff = [30, 60, 120];

    protected ?string $email;

    /**
     * Create a new notification instance.
     */
    public function __construct($account)
    {
        $this->account = $account;
        $this->email = $account->customer->email;
        $this->onQueue('emails');
        $this->afterCommit();
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function shouldSend(object $notifiable, string $channel): bool
    {
        return ! $notifiable->verified_at && $notifiable->customer->email === $this->email;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $urls = new \Illuminate\Routing\UrlGenerator(app('router')->getRoutes(), request());
        $urls->setKeyResolver(fn () => config('app.key'));
        $urls->forceRootUrl(config('app.url'));
        $urls->forceScheme(parse_url(config('app.url'), PHP_URL_SCHEME));
        $url = $urls->temporarySignedRoute('card.verification.verify', now()->addHour(), [
            'public_token' => $this->account->public_token,
            'id' => $this->account->id,
            'hash' => sha1($this->email),
        ]);

        return (new MailMessage)
            ->view('emails.notification')
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
