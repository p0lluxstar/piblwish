<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Письмо с кодом подтверждения нового email; отправляется на новый адрес
 */
class EmailChangeCodeMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $username,
        public string $code,
        public int $expiresInMinutes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Код подтверждения нового email',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.email-change-code',
        );
    }
}
