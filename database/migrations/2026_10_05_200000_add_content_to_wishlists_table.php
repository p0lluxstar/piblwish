<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Текст заметки (тип note) и необязательное название.
     *
     * У заметки нет названия и позиций: всё её содержимое хранится в content.
     * У списков желаний и дел content остаётся null, а название обязательно
     * на уровне валидации запросов.
     */
    public function up(): void
    {
        Schema::table('wishlists', function (Blueprint $table) {
            $table->string('title')->nullable()->change();
            $table->text('content')->nullable()->after('title');
        });
    }

    public function down(): void
    {
        // Название снова обязательно: у заметок его нет, поэтому подставляется пустая строка
        DB::table('wishlists')->whereNull('title')->update(['title' => '']);

        Schema::table('wishlists', function (Blueprint $table) {
            $table->dropColumn('content');
            $table->string('title')->nullable(false)->change();
        });
    }
};
