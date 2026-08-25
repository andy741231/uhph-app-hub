<?php

namespace App\Mail;

use App\Models\ConflictOfInterestDeclaration;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ConflictOfInterestDeclared extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $reviewer,
        public ConflictOfInterestDeclaration $declaration,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'UH Grants Portal — Conflict of Interest declared by '.$this->reviewer->full_name,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.conflict-of-interest',
        );
    }
}
