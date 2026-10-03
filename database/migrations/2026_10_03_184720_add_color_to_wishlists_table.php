<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Цвет фона списка.
     *
     * Хранится ключ цвета (white, lavender, …), а не HEX: оттенки задаются
     * во фронтенде. Допустимые значения — в App\Enums\WishlistColor.
     * Существующие списки получают white, то есть прежний вид.
     */
    public function up(): void
    {
        Schema::table('wishlists', function (Blueprint $table) {
            $table->string('color', 20)
                ->default('white')
                ->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('wishlists', function (Blueprint $table) {
            $table->dropColumn('color');
        });
    }
};
