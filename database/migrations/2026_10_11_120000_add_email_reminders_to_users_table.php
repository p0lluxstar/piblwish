<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Письма-напоминания о сроке своих и сохранённых чужих списков.
     *
     * email_reminders включает напоминания (по умолчанию, в том числе у существующих
     * пользователей, включены; выключаются переключателем в окне настроек или
     * ссылкой отписки в письме), reminder_days — за сколько дней до даты
     * списка (1–7, по умолчанию 3) приходит напоминание.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('email_reminders')
                ->default(true)
                ->after('show_friends_events');

            $table->unsignedTinyInteger('reminder_days')
                ->default(3)
                ->after('email_reminders');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['email_reminders', 'reminder_days']);
        });
    }
};
