<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Приоритет позиции (1–3, см. App\Enums\WishlistItemPriority).
     *
     * Необязателен: у существующих позиций остаётся null, то есть приоритет не указан.
     */
    public function up(): void
    {
        Schema::table('wishlist_items', function (Blueprint $table) {
            $table->unsignedTinyInteger('priority')
                ->nullable()
                ->after('url');
        });
    }

    public function down(): void
    {
        Schema::table('wishlist_items', function (Blueprint $table) {
            $table->dropColumn('priority');
        });
    }
};
