<?php

namespace App\Mail;

use App\Models\Submission;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReviewsAvailable extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $recipient,
        public Submission $submission,
        public string $viewUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'UH Grants Portal — Reviews available for '.$this->submission->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.reviews-available',
        );
    }
}
