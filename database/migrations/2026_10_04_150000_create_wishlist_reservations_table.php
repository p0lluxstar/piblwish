<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Брони гостей: одна запись на одно сохранение выбора на общей странице.
     *
     * Гость получает случайный токен, в БД хранится только его SHA-256.
     * По токену гость может отменить любые позиции своей брони.
     * Позиции, выбранные до появления броней, остаются без reservation_id.
     */
    public function up(): void
    {
        Schema::create('wishlist_reservations', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('wishlist_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('token_hash', 64)->unique();

            $table->timestamps();
        });

        Schema::table('wishlist_items', function (Blueprint $table) {
            $table->foreignUlid('reservation_id')
                ->nullable()
                ->after('is_selected')
                ->constrained('wishlist_reservations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('wishlist_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reservation_id');
        });

        Schema::dropIfExists('wishlist_reservations');
    }
};
