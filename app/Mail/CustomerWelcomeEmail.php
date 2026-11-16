<?php

namespace App\Mail;

use App\Models\Customer;
use App\Models\LoyaltyAccount;
use App\Support\CustomerEmailBrand;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CustomerWelcomeEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = [60, 300, 900]; // 1 min, 5 min, 15 min

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Customer $customer,
        public LoyaltyAccount $loyaltyAccount,
        public string $verificationToken
    ) {
        $this->onQueue('emails');
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: CustomerEmailBrand::from($this->loyaltyAccount->store),
            replyTo: CustomerEmailBrand::replyTo($this->loyaltyAccount->store),
            subject: 'Welcome to '.$this->loyaltyAccount->store->name.' — your loyalty card is ready',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        // Use APP_URL to ensure correct domain in production
        $baseUrl = config('app.url');
        $verificationUrl = $baseUrl.'/verify-email/'.$this->verificationToken.'?card='.$this->loyaltyAccount->public_token;

        return new Content(
            view: 'emails.customer-welcome',
            with: [
                'customerName' => $this->customer->name ?? 'there',
                'store' => $this->loyaltyAccount->store,
                'verificationUrl' => $verificationUrl,
                'requiresVerification' => $this->loyaltyAccount->requires_verification_for_redemption,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        // Log the failure with context
        \Log::error('Customer welcome email job failed', [
            'customer_id' => $this->customer->id,
            'loyalty_account_id' => $this->loyaltyAccount->id,
            'email' => $this->customer->email,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts ?? 0,
        ]);

        // Check for SendGrid-specific errors
        $errorMessage = $exception->getMessage();
        if (str_contains($errorMessage, 'Maximum credits exceeded') ||
            str_contains($errorMessage, 'Authentication failed') ||
            str_contains($errorMessage, 'Failed to authenticate')) {
            \Log::warning('SendGrid service issue detected - consider checking account status', [
                'error' => $errorMessage,
            ]);
        }
    }
}
