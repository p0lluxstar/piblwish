<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Чужие списки, которые пользователь добавил к себе с общей страницы.
     *
     * Запись — только закладка: данные списка берутся из самого списка.
     * Пара пользователь — список уникальна. Удаление списка удаляет и закладки;
     * при удалении аккаунта запись пользователя остаётся (deactivated_at),
     * поэтому его закладки удаляет UserService::deleteAccount.
     */
    public function up(): void
    {
        Schema::create('saved_wishlists', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignUlid('wishlist_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['user_id', 'wishlist_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_wishlists');
    }
};
