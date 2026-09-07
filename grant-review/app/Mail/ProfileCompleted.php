<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProfileCompleted extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->user->isReviewer()
                ? 'Pilot Central — New reviewer profile completed. Please send COI notification'
                : 'Pilot Central — New user profile completed',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.profile-completed',
        );
    }
}
