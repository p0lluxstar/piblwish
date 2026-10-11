<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Разрешение гостям отмечать дела выполненными по общей ссылке.
     *
     * Учитывается только у списка дел с включённым доступом по ссылке (is_shared).
     * Гость может только отметить дело; снимает отметку только владелец.
     * Существующие списки получают false: гости их только просматривают.
     */
    public function up(): void
    {
        Schema::table('wishlists', function (Blueprint $table) {
            $table->boolean('guests_can_check')
                ->default(false)
                ->after('is_shared');
        });
    }

    public function down(): void
    {
        Schema::table('wishlists', function (Blueprint $table) {
            $table->dropColumn('guests_can_check');
        });
    }
};
