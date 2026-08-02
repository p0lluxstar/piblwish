<?php

namespace App\Services\SharedWishlist;

use App\Models\Wishlist;

class SharedWishlistService
{
    public function getWishlistById(
        string $id
    ): Wishlist {
        return Wishlist::query()
            ->with(['items', 'user'])
            ->findOrFail($id);
    }
}
