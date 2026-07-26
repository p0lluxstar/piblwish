<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Wishlist\WishlistCollection;
use Illuminate\Http\Request;
use App\Http\Resources\Wishlist\WishlistResource;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\Wishlist\CreateWishlistRequest;
use App\Services\Wishlist\WishlistService;
use Illuminate\Support\Facades\Log;
use App\Http\Requests\Wishlist\UpdateWishlistRequest;

class WishlistController extends Controller
{
    public function __construct(
        private readonly WishlistService $wishlistService
    ) {}

    // Получить все вишлисты текущего пользователя
    public function getUserWishlists(
        Request $request
    ): WishlistCollection {
        $wishlists = $this->wishlistService
            ->getUserWishlists($request->user());

        return new WishlistCollection($wishlists);
    }

    // Создать новый вишлист
    public function createWishlist(
        CreateWishlistRequest $request
    ): WishlistResource {
        $wishlist = $this->wishlistService
            ->createWishlist(
                $request->user(),
                $request->validated()
            );

        return new WishlistResource($wishlist);
    }

    // Получить конкретный вишлист по ID
    public function getWishlistById(
        Request $request,
        string $id
    ): WishlistResource {
        Log::info('Wishlist ID:', [
            'id' => $id,
        ]);

        $wishlist = $this->wishlistService
            ->getWishlistById(
                $request->user(),
                $id
            );

        return new WishlistResource($wishlist);
    }


    // Обновить вишлист по ID
    public function updateWishlist(
        UpdateWishlistRequest $request,
        string $id
    ): WishlistResource {
        $wishlist = $this->wishlistService->updateWishlist(
            $request->user(),
            $id,
            $request->validated()
        );

        return new WishlistResource($wishlist);
    }

    // Удалить список желаний
    public function deleteWishlist(
        Request $request,
        string $id
    ): \Illuminate\Http\JsonResponse {
        $this->wishlistService->deleteWishlist(
            $request->user(),
            $id
        );
        return response()->json([
            'message' => 'Wishlist deleted successfully',
        ]);
    }
}
