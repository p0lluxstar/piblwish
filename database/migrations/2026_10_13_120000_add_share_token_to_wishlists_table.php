<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Токен общей ссылки списка: по нему список открывается гостям вместо id.
     *
     * У нового списка токен совпадает с id (Wishlist::booted), поэтому уже
     * разосланные ссылки продолжают работать. Владелец может сменить ссылку
     * (WishlistService::regenerateShareToken): прежняя перестаёт открывать список.
     *
     * Закладка «Чужие списки» запоминает токен, по которому список добавили:
     * после смены ссылки она становится недоступной, пока пользователь
     * не откроет новую ссылку и не добавит список снова.
     */
    public function up(): void
    {
        Schema::table('wishlists', function (Blueprint $table) {
            $table->string('share_token', 26)->nullable()->after('id');
        });

        DB::table('wishlists')->update(['share_token' => DB::raw('id')]);

        Schema::table('wishlists', function (Blueprint $table) {
            $table->string('share_token', 26)->nullable(false)->change();
            $table->unique('share_token');
        });

        Schema::table('saved_wishlists', function (Blueprint $table) {
            $table->string('share_token', 26)->nullable()->after('wishlist_id');
        });

        DB::table('saved_wishlists')->update(['share_token' => DB::raw('wishlist_id')]);

        Schema::table('saved_wishlists', function (Blueprint $table) {
            $table->string('share_token', 26)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('saved_wishlists', function (Blueprint $table) {
            $table->dropColumn('share_token');
        });

        Schema::table('wishlists', function (Blueprint $table) {
            $table->dropUnique(['share_token']);
            $table->dropColumn('share_token');
        });
    }
};
