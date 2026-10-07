<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Запросы на смену email.
     *
     * Новый адрес применяется только после ввода кода, отправленного на него.
     * У пользователя не больше одного запроса: новый заменяет предыдущий.
     * Хранится только хеш кода; после 5 неверных попыток запрос удаляется.
     */
    public function up(): void
    {
        Schema::create('email_change_requests', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('user_id')
                ->unique()
                ->constrained()
                ->cascadeOnDelete();

            $table->string('new_email');
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_change_requests');
    }
};
