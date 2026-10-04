<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Режим сюрприза: владелец не видит, какие позиции выбрали гости.
     *
     * Флаг влияет только на ответы владельцу (/v1/wishlists); на общей
     * странице гости по-прежнему видят выбранные позиции. Существующие
     * списки получают false, то есть прежнее поведение.
     */
    public function up(): void
    {
        Schema::table('wishlists', function (Blueprint $table) {
            $table->boolean('hide_selections')
                ->default(false)
                ->after('color');
        });
    }

    public function down(): void
    {
        Schema::table('wishlists', function (Blueprint $table) {
            $table->dropColumn('hide_selections');
        });
    }
};
