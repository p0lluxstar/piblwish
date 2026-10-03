<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Уведомление о смене пароля
 */
class PasswordChangedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $username,
        public string $changedAt,
        public ?string $ipAddress,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Пароль от аккаунта изменён',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.password-changed',
        );
    }
}
