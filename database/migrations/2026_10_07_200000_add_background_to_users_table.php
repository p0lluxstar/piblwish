<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Фон приложения, выбранный пользователем.
     *
     * Хранится ключ фона (blossom, ocean, …), а не цвета: они задаются
     * во фронтенде. Допустимые значения — в App\Enums\AppBackground.
     * Существующие пользователи получают blossom, то есть прежний фон.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('background', 20)
                ->default('blossom')
                ->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('background');
        });
    }
};
