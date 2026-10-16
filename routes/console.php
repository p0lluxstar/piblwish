<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Напоминания о списках, дата которых наступает в пределах срока пользователя.
// Повторный запуск писем не дублирует (WishlistReminder).
// Монитор Sentry Crons сообщает, если запуск пропущен, упал или не закончился
// за maxRuntime; расписание и пояс берутся из задачи. Без DSN отметки не отправляются
Schedule::command('wishlists:send-due-reminders')
    ->dailyAt(config('wishlist.reminders.time'))
    ->timezone(config('wishlist.reminders.timezone'))
    ->withoutOverlapping()
    ->sentryMonitor(
        monitorSlug: 'due-date-reminders',
        checkInMargin: 10,
        maxRuntime: 15,
    );
