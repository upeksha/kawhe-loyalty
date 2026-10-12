<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class StoreStaffInvitationMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public $tries = 3;

    public $backoff = [30, 60, 120];

    public function __construct(public string $invitationUrl, public string $storeName)
    {
        $this->onQueue('emails');
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Join the team at '.$this->storeName);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.store-staff-invitation');
    }
}
