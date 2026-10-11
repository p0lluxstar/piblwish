<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Время переноса списка в архив; null — список не в архиве.
     *
     * Архивный список доступен владельцу только для просмотра и не открывается
     * по ссылке, при этом is_shared, брони и совместные подарки не меняются
     * и действуют снова после восстановления. Индекс нужен для выборки
     * активных и архивных списков пользователя.
     */
    public function up(): void
    {
        Schema::table('wishlists', function (Blueprint $table) {
            $table->timestamp('archived_at')
                ->nullable()
                ->after('due_date');

            $table->index(['user_id', 'archived_at']);
        });
    }

    public function down(): void
    {
        Schema::table('wishlists', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'archived_at']);
            $table->dropColumn('archived_at');
        });
    }
};
