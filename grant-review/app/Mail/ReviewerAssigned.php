<?php

namespace App\Mail;

use App\Models\Submission;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReviewerAssigned extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $reviewer,
        public Submission $submission,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'UH Grants Portal — You have been assigned to review a proposal',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.reviewer-assigned',
        );
    }
}
