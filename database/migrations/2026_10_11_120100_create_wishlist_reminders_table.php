<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Отправленные напоминания о сроке списка: одна запись на пользователя,
     * список и дату, о которой он предупреждён.
     *
     * Запись не позволяет отправить напоминание повторно, если рассылка
     * запущена ещё раз в тот же день. Дата входит в ключ, поэтому после
     * переноса срока владельцем напоминание о новой дате придёт снова.
     */
    public function up(): void
    {
        Schema::create('wishlist_reminders', function (Blueprint $table) {
            $table->id();

            $table->foreignUlid('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignUlid('wishlist_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->date('due_date');

            $table->timestamp('created_at')->nullable();

            $table->unique(['user_id', 'wishlist_id', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wishlist_reminders');
    }
};
