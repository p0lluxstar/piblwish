<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Доступ к списку дел по ссылке только для просмотра.
     *
     * Флаг учитывается только у списка дел: список желаний доступен по ссылке
     * всегда, заметка — никогда. Существующие списки дел получают false,
     * то есть остаются личными.
     */
    public function up(): void
    {
        Schema::table('wishlists', function (Blueprint $table) {
            $table->boolean('is_shared')
                ->default(false)
                ->after('hide_selections');
        });
    }

    public function down(): void
    {
        Schema::table('wishlists', function (Blueprint $table) {
            $table->dropColumn('is_shared');
        });
    }
};
