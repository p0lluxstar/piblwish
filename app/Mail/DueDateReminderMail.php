<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

/**
 * Напоминание о списках, дата которых скоро наступает: свои списки
 * и сохранённые чужие списки (DueDateReminderService)
 */
class DueDateReminderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  array<int, array{title: ?string, type: string, date: string, details: ?string}>  $ownLists
     * @param  array<int, array{title: ?string, type: string, owner: string, date: string, details: ?string, url: string}>  $savedLists
     */
    public function __construct(
        public string $username,
        // Срок напоминания из настроек: «1 день», «3 дня»
        public string $reminderDays,
        public array $ownLists,
        public array $savedLists,
        public string $dashboardUrl,
        public string $unsubscribeUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Скоро наступает дата ваших списков',
        );
    }

    // Почтовые клиенты показывают по этим заголовкам кнопку отписки;
    // List-Unsubscribe-Post разрешает отписку одним POST-запросом (RFC 8058)
    public function headers(): Headers
    {
        return new Headers(
            text: [
                'List-Unsubscribe' => "<{$this->unsubscribeUrl}>",
                'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.due-date-reminder',
        );
    }
}
