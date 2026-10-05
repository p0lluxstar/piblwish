<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Стоимость позиции в целых рублях.
     *
     * Необязательна: у существующих позиций остаётся null, то есть стоимость не указана.
     */
    public function up(): void
    {
        Schema::table('wishlist_items', function (Blueprint $table) {
            $table->unsignedInteger('price')
                ->nullable()
                ->after('priority');
        });
    }

    public function down(): void
    {
        Schema::table('wishlist_items', function (Blueprint $table) {
            $table->dropColumn('price');
        });
    }
};
