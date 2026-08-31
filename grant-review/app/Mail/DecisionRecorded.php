<?php

namespace App\Mail;

use App\Models\Decision;
use App\Models\Submission;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DecisionRecorded extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Submission $submission,
        public Decision $decision,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'UH Grants Portal — Decision recorded for your proposal',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.decision-recorded',
        );
    }
}
