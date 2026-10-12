<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Статистика пользователя: сколько карточек каждого типа он создал за всё время.
     *
     * Счётчики увеличиваются при создании списка и не уменьшаются при его удалении,
     * поэтому подсчитать их по таблице wishlists нельзя. Существующим пользователям
     * они заполняются числом списков, которые есть сейчас: удалённые раньше списки
     * не сохранились.
     */
    private const TYPES = ['gift', 'todo', 'note', 'fund'];

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $after = 'reminder_days';

            foreach (self::TYPES as $type) {
                $table->unsignedInteger("created_{$type}_count")
                    ->default(0)
                    ->after($after);

                $after = "created_{$type}_count";
            }
        });

        foreach (self::TYPES as $type) {
            DB::table('users')->update([
                "created_{$type}_count" => DB::table('wishlists')
                    ->selectRaw('count(*)')
                    ->whereColumn('wishlists.user_id', 'users.id')
                    ->where('wishlists.type', $type),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(array_map(
                fn (string $type): string => "created_{$type}_count",
                self::TYPES
            ));
        });
    }
};
