<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Тип списка: gift — список желаний, todo — список дел.
     *
     * Допустимые значения — в App\Enums\WishlistType.
     * Существующие списки получают gift, то есть остаются списками желаний.
     */
    public function up(): void
    {
        Schema::table('wishlists', function (Blueprint $table) {
            $table->string('type', 20)
                ->default('gift')
                ->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('wishlists', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
