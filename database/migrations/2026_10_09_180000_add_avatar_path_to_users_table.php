<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Фотография пользователя вместо буквы в кружке.
     *
     * Хранится путь к файлу на диске public, например
     * avatars/{id пользователя}/{ULID}.webp, а не само изображение.
     * null — фотографии нет, показывается первая буква имени.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar_path')
                ->nullable()
                ->after('background');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('avatar_path');
        });
    }
};
