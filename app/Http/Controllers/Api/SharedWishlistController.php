<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SharedWishlist\SharedWishlistResource;
use App\Services\SharedWishlist\SharedWishlistService;
use Illuminate\Support\Facades\Log;

class SharedWishlistController extends Controller
{
    public function __construct(
        private readonly SharedWishlistService $sharedWishlistService
    ) {}

    // Получить конкретный вишлист по ID
    public function getWishlistById(
        string $id
    ): SharedWishlistResource {
        // Log::info('Wishlist ID:', [
        //     'id' => $id,
        // ]);

        $wishlist = $this->sharedWishlistService
            ->getWishlistById(
                $id
            );

        return new SharedWishlistResource($wishlist);
    }
}
