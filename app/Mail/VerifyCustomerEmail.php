<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VerifyCustomerEmail extends Mailable implements ShouldQueue
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
        public string $token,
        public string $publicToken
    ) {
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Verify your Kawhe Loyalty email',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        // Use APP_URL to ensure correct domain in production
        $baseUrl = config('app.url');
        $verificationUrl = $baseUrl . '/verify-email/' . $this->token . '?card=' . $this->publicToken;

        return new Content(
            view: 'emails.verify-customer-email',
            with: [
                'verificationUrl' => $verificationUrl,
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
     *
     * @param  \Throwable  $exception
     * @return void
     */
    public function failed(\Throwable $exception): void
    {
        // Log the failure with context
        \Log::error('Verification email job failed', [
            'token' => substr($this->token, 0, 10) . '...',
            'public_token' => $this->publicToken,
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
