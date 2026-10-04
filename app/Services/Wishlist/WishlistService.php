<?php

namespace App\Services\Wishlist;

use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class WishlistService
{
    public function getUserWishlists(User $user): Collection
    {
        return $user->wishlists()
            ->with('items')
            ->latest()
            ->get();
    }

    public function createWishlist(
        User $user,
        array $data
    ): Wishlist {
        return DB::transaction(function () use (
            $user,
            $data
        ) {
            // Если цвет и режим сюрприза не переданы, их задают значения по умолчанию в модели
            $wishlist = $user->wishlists()->create(
                $this->wishlistAttributes($data)
            );

            // Порядок позиций задаётся порядком массива items
            $wishlist->items()->createMany(
                collect($data['items'])
                    ->values()
                    ->map(fn($item, $index) => [
                        'description' => $item['label'],
                        'url' => $item['url'] ?? null,
                        'is_selected' => false,
                        'position' => $index,
                    ])
                    ->toArray()
            );

            return $wishlist->load('items');
        });
    }

    public function updateWishlist(User $user, string $id, array $data): Wishlist
    {
        return DB::transaction(function () use ($user, $id, $data) {
            $wishlist = Wishlist::query()
                ->where('user_id', $user->id)
                ->where('id', $id)
                ->firstOrFail();

            // Владелец не видел выбор гостей, если режим сюрприза был включён
            // до этого запроса: тогда isSelected из запроса не учитывается
            $selectionsHidden = $wishlist->hide_selections;

            // Обновляются только переданные поля
            $attributes = $this->wishlistAttributes($data);

            if ($attributes !== []) {
                $wishlist->update($attributes);
            }

            if (array_key_exists('items', $data)) {
                // Выбор гостей по id прежних позиций этого списка. Блокировка строк
                // не даёт гостю отметить позицию между чтением и удалением
                $previousSelections = $wishlist->items()
                    ->lockForUpdate()
                    ->pluck('is_selected', 'id');

                $wishlist->items()->delete();

                // Порядок позиций задаётся порядком массива items
                $items = collect($data['items'])
                    ->values()
                    ->map(fn($item, $index) => [
                        'description' => $item['label'],
                        'url' => $item['url'] ?? null,
                        'is_selected' => $selectionsHidden
                            ? (bool) $previousSelections->get((string) ($item['id'] ?? ''), false)
                            : (bool) ($item['isSelected'] ?? false),
                        'position' => $index,
                    ])
                    ->toArray();

                $wishlist->items()->createMany($items);
            }

            return $wishlist->load('items');
        });
    }

    // Поля списка из запроса в атрибуты модели; непереданные поля не попадают в результат
    private function wishlistAttributes(array $data): array
    {
        $attributes = Arr::only($data, ['title', 'color']);

        if (array_key_exists('hideSelections', $data)) {
            $attributes['hide_selections'] = (bool) $data['hideSelections'];
        }

        return $attributes;
    }

    public function deleteWishlist(User $user, string $id): void
    {
        DB::transaction(function () use ($user, $id) {
            $wishlist = Wishlist::query()
                ->where('user_id', $user->id)
                ->where('id', $id)
                ->firstOrFail();
            $wishlist->delete();
        });
    }
}
