<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Порядковый номер позиции внутри списка (с нуля).
     *
     * Порядок задаёт владелец в модалках создания и редактирования:
     * позиции сохраняются в порядке массива items. Существующие позиции
     * нумеруются по дате создания, то есть сохраняют прежний порядок.
     */
    public function up(): void
    {
        Schema::table('wishlist_items', function (Blueprint $table) {
            $table->unsignedInteger('position')
                ->default(0)
                ->after('is_selected');
        });

        $currentWishlistId = null;
        $position = 0;

        DB::table('wishlist_items')
            ->select(['id', 'wishlist_id'])
            ->orderBy('wishlist_id')
            ->orderBy('created_at')
            ->orderBy('id')
            ->lazy()
            ->each(function (object $item) use (&$currentWishlistId, &$position) {
                if ($item->wishlist_id !== $currentWishlistId) {
                    $currentWishlistId = $item->wishlist_id;
                    $position = 0;
                }

                DB::table('wishlist_items')
                    ->where('id', $item->id)
                    ->update(['position' => $position++]);
            });
    }

    public function down(): void
    {
        Schema::table('wishlist_items', function (Blueprint $table) {
            $table->dropColumn('position');
        });
    }
};
