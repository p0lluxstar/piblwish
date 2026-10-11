<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Доступ по ссылке теперь настраивается и у списка желаний.
     *
     * До этого список желаний открывался по ссылке всегда, а is_shared
     * у него оставался false. Существующие списки желаний получают true,
     * чтобы уже разосланные ссылки продолжали работать.
     */
    public function up(): void
    {
        DB::table('wishlists')
            ->where('type', 'gift')
            ->update(['is_shared' => true]);
    }

    public function down(): void
    {
        // Прежняя схема флаг у списка желаний не учитывала
        DB::table('wishlists')
            ->where('type', 'gift')
            ->update(['is_shared' => false]);
    }
};
