<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Дата списка: у списка дел — срок, до которого нужно всё сделать,
     * у списка желаний — дата события (день рождения, праздник).
     *
     * Хранится только дата без времени: число оставшихся дней фронтенд считает
     * по местной дате пользователя. У заметки даты нет. Существующие списки
     * получают null — дата не указана.
     */
    public function up(): void
    {
        Schema::table('wishlists', function (Blueprint $table) {
            $table->date('due_date')
                ->nullable()
                ->after('guest_name_required');
        });
    }

    public function down(): void
    {
        Schema::table('wishlists', function (Blueprint $table) {
            $table->dropColumn('due_date');
        });
    }
};
