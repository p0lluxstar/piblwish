<?php

namespace App\Console\Commands;

use App\Services\Reminder\DueDateReminderService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

// Ежедневная рассылка напоминаний о списках, дата которых наступает в пределах
// срока, выбранного пользователем (1–7 дней);
// расписание задано в routes/console.php
class SendDueDateReminders extends Command
{
    protected $signature = 'wishlists:send-due-reminders';

    protected $description = 'Отправить напоминания о списках, дата которых скоро наступает';

    public function handle(DueDateReminderService $reminderService): int
    {
        $today = CarbonImmutable::now(config('wishlist.reminders.timezone'));

        $sent = $reminderService->sendReminders($today);

        // По журналу видно, что рассылка запускалась, даже если писем не было
        Log::info('Due date reminders sent', ['count' => $sent]);

        $this->info("Отправлено писем: {$sent}");

        return self::SUCCESS;
    }
}
