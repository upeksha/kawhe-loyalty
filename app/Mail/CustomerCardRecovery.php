<?php

namespace App\Mail;

use App\Models\Store;
use App\Support\CustomerEmailBrand;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CustomerCardRecovery extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $tries = 3;

    public $backoff = [30, 60, 120];

    public bool $joining = false;

    public ?Store $store = null;

    public function __construct(public string $recoveryUrl, public string $storeName, bool $joining = false, ?Store $store = null)
    {
        $this->joining = $joining;
        $this->store = $store;
        $this->onQueue('emails');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: CustomerEmailBrand::from($this->store, $this->storeName),
            replyTo: CustomerEmailBrand::replyTo($this->store),
            subject: ($this->joining ? 'Continue to ' : 'Open your loyalty card at ').$this->storeName,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.customer-card-recovery');
    }
}
