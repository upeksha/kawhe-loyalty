<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class CustomerCardRecovery extends Mailable implements ShouldQueue
{
    use Queueable;

    public $tries = 3;

    public $backoff = [30, 60, 120];

    public function __construct(public string $recoveryUrl, public string $storeName)
    {
        $this->onQueue('emails');
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Open your loyalty card at '.$this->storeName);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.customer-card-recovery');
    }
}
