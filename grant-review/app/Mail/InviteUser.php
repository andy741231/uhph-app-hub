<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InviteUser extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $setPasswordUrl,
        public string $recipientName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'UH Grants Portal — Set Your Password',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.invite',
        );
    }
}
