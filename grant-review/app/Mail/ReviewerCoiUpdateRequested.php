<?php

namespace App\Mail;

use App\Models\ReviewerRoundInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReviewerCoiUpdateRequested extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ReviewerRoundInvitation $invitation,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Pilot Central — Please update your COI declaration',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.reviewer-coi-update-requested',
        );
    }
}
