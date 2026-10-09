<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Несколько ссылок на товар у позиции списка вместо одной.
     *
     * Колонка url заменяется JSON-массивом urls (до трёх адресов, null — ссылок нет).
     * Ограничение на количество и схему http(s) проверяется при валидации запроса.
     * Прежняя ссылка переносится в массив первым элементом.
     */
    public function up(): void
    {
        Schema::table('wishlist_items', function (Blueprint $table) {
            $table->json('urls')
                ->nullable()
                ->after('description');
        });

        DB::table('wishlist_items')
            ->whereNotNull('url')
            ->orderBy('id')
            ->each(function (object $item) {
                DB::table('wishlist_items')
                    ->where('id', $item->id)
                    ->update(['urls' => json_encode([$item->url])]);
            });

        Schema::table('wishlist_items', function (Blueprint $table) {
            $table->dropColumn('url');
        });
    }

    // При откате сохраняется только первая ссылка позиции
    public function down(): void
    {
        Schema::table('wishlist_items', function (Blueprint $table) {
            $table->string('url', 2048)
                ->nullable()
                ->after('description');
        });

        DB::table('wishlist_items')
            ->whereNotNull('urls')
            ->orderBy('id')
            ->each(function (object $item) {
                $urls = json_decode($item->urls, true);

                DB::table('wishlist_items')
                    ->where('id', $item->id)
                    ->update(['url' => is_array($urls) ? ($urls[0] ?? null) : null]);
            });

        Schema::table('wishlist_items', function (Blueprint $table) {
            $table->dropColumn('urls');
        });
    }
};
