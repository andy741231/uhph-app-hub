<?php

namespace App\Mail;

use App\Models\Submission;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AllReviewsComplete extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Submission $submission,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'UH Grants Portal — All reviews complete for '.$this->submission->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.all-reviews-complete',
        );
    }
}
