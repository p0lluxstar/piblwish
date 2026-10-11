<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Уведомление о смене email; отправляется на прежний адрес
 */
class EmailChangedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $username,
        public string $newEmail,
        public string $changedAt,
        public ?string $ipAddress,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Email аккаунта изменён',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.email-changed',
        );
    }
}
