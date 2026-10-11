<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Напоминания о списках, дата которых наступает в пределах срока пользователя.
// Повторный запуск писем не дублирует (WishlistReminder)
Schedule::command('wishlists:send-due-reminders')
    ->dailyAt(config('wishlist.reminders.time'))
    ->timezone(config('wishlist.reminders.timezone'))
    ->withoutOverlapping();
