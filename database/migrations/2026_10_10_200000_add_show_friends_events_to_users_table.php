<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Показ строки «Скоро у друзей» в дашборде: ближайшие даты чужих списков.
     *
     * Настройка включена по умолчанию, в том числе у существующих пользователей;
     * выключается переключателем в окне настроек аккаунта.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('show_friends_events')
                ->default(true)
                ->after('background');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('show_friends_events');
        });
    }
};
