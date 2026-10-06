<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Совместные подарки: гость, выбравший позицию, предлагает остальным
     * гостям подарить её вместе и оставляет имя, контакт и комментарий.
     *
     * Одна запись на позицию. Запись удаляется вместе с позицией и вместе
     * с бронью организатора. Деньги и реквизиты не хранятся: участники
     * договариваются о переводе сами.
     */
    public function up(): void
    {
        Schema::create('wishlist_joint_gifts', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('item_id')
                ->unique()
                ->constrained('wishlist_items')
                ->cascadeOnDelete();

            // Бронь организатора: по её токену он отменяет совместный подарок
            $table->foreignUlid('reservation_id')
                ->constrained('wishlist_reservations')
                ->cascadeOnDelete();

            $table->string('organizer_name', 50);
            $table->string('contact', 100)->nullable();
            $table->string('comment', 200)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wishlist_joint_gifts');
    }
};
