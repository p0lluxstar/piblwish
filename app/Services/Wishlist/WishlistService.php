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
            // Если цвет не передан, его задаёт значение по умолчанию в модели (white)
            $wishlist = $user->wishlists()->create(
                Arr::only($data, ['title', 'color'])
            );

            $wishlist->items()->createMany(
                collect($data['items'])
                    ->map(fn($item) => [
                        'description' => $item['label'],
                        'is_selected' => false,
                    ])
                    ->toArray()
            );

            return $wishlist->load('items');
        });
    }

    public function updateWishlist(User $user, string $id, array $data): Wishlist
    {

        // logger('Входные данные для обновления:', $data);

        return DB::transaction(function () use ($user, $id, $data) {
            $wishlist = Wishlist::query()
                ->where('user_id', $user->id)
                ->where('id', $id)
                ->firstOrFail();

            // Обновляются только переданные поля
            $attributes = Arr::only($data, ['title', 'color']);

            if ($attributes !== []) {
                $wishlist->update($attributes);
            }

            if (array_key_exists('items', $data)) {
                $wishlist->items()->delete();

                $items = collect($data['items'])
                    ->map(fn($item) => [
                        'description' => $item['label'],
                        'is_selected' => (bool) ($item['isSelected'] ?? false),
                    ])
                    ->toArray();

                $wishlist->items()->createMany($items);
            }

            return $wishlist->load('items');
        });
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
