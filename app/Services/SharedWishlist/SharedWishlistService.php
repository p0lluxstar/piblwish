<?php

namespace App\Services\SharedWishlist;

use App\Models\Wishlist;
use App\Models\WishlistItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SharedWishlistService
{
    // Получить конкретный вишлист по ID
    public function getWishlistById(
        string $id
    ): Wishlist {
        return Wishlist::query()
            ->with(['items', 'user'])
            ->findOrFail($id);
    }

    // Обновить выбранные элементы в списке желаний
    public function updateSharedWishlistItems(
        string $wishlistId,
        array $itemIds
    ): Wishlist {
        DB::transaction(function () use ($wishlistId, $itemIds) {

            foreach ($itemIds as $itemId) {

                $updated = WishlistItem::query()
                    ->where('wishlist_id', $wishlistId)
                    ->where('id', $itemId)
                    ->where('is_selected', false)
                    ->update([
                        'is_selected' => true,
                    ]);

                if ($updated === 0) {
                    throw ValidationException::withMessages([
                        'items' => 'One or more items have already been selected.',
                    ]);
                }
            }
        });

        return Wishlist::with('items')->findOrFail($wishlistId);
    }
}
