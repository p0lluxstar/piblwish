<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Кто отметил дело выполненным.
     *
     * wishlists.guest_name_required — должен ли гость указать имя, отмечая дела
     * по ссылке (учитывается при guests_can_check). По умолчанию true.
     *
     * wishlist_items.checked_by_guest — дело отметил гость по ссылке, а не владелец;
     * wishlist_items.checked_by_name — имя, которое указал гость, или null.
     * Существующие отметки получают false: до появления отметок гостями
     * дела отмечал только владелец.
     */
    public function up(): void
    {
        Schema::table('wishlists', function (Blueprint $table) {
            $table->boolean('guest_name_required')
                ->default(true)
                ->after('guests_can_check');
        });

        Schema::table('wishlist_items', function (Blueprint $table) {
            $table->boolean('checked_by_guest')
                ->default(false)
                ->after('is_selected');
            $table->string('checked_by_name', 50)
                ->nullable()
                ->after('checked_by_guest');
        });
    }

    public function down(): void
    {
        Schema::table('wishlist_items', function (Blueprint $table) {
            $table->dropColumn(['checked_by_guest', 'checked_by_name']);
        });

        Schema::table('wishlists', function (Blueprint $table) {
            $table->dropColumn('guest_name_required');
        });
    }
};
