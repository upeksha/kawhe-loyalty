<?php

namespace App\Mail;

use App\Models\MerchantSignupAlert;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class MerchantSignupAlertEmail extends Mailable
{
    public function __construct(public MerchantSignupAlert $alert) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->alert->event === 'verified'
            ? '[Kawhe] Café email verified'
            : '[Kawhe] New café signup');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.merchant-signup-alert');
    }
}
