<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SharedWishlist\SharedWishlistResource;
use App\Services\SharedWishlist\SharedWishlistService;
use Illuminate\Support\Facades\Log;
use App\Http\Requests\Wishlist\UpdateWishlistRequest;
use App\Http\Requests\SharedWishlist\UpdateSharedWishlistItemsRequest;

class SharedWishlistController extends Controller
{
    public function __construct(
        private readonly SharedWishlistService $sharedWishlistService
    ) {}

    // Получить конкретный вишлист по ID
    public function getWishlistById(
        string $id
    ): SharedWishlistResource {
        Log::info('Wishlist ID:', [
            'id' => $id,
        ]);

        $wishlist = $this->sharedWishlistService
            ->getWishlistById(
                $id
            );

        return new SharedWishlistResource($wishlist);
    }

    // Обновить выбранные элементы в списке желаний
    public function updateSharedWishlistItems(
        UpdateSharedWishlistItemsRequest $request,
        string $wishlistId
    ): SharedWishlistResource {

        Log::info('Обновление элементов списка желаний', [
            'wishlist_id' => $wishlistId
        ]);

        $wishlist = $this->sharedWishlistService->updateSharedWishlistItems(
            $wishlistId,
            $request->validated()['item_ids']
        );

        return new SharedWishlistResource($wishlist);
    }
}
