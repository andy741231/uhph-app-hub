<?php

namespace App\Mail;

use App\Models\Review;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReviewSubmitted extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $reviewer,
        public Submission $submission,
        public Review $review,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'UH Grants Portal — Review submitted by '.$this->reviewer->full_name,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.review-submitted',
        );
    }
}
