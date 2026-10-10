<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SavedWishlist\SavedWishlistCollection;
use App\Http\Resources\SavedWishlist\SavedWishlistResource;
use App\Services\SavedWishlist\SavedWishlistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// Раздел «Чужие списки»: списки других пользователей, добавленные к себе с общей страницы
class SavedWishlistController extends Controller
{
    public function __construct(
        private readonly SavedWishlistService $savedWishlistService
    ) {}

    public function getSavedWishlists(Request $request): SavedWishlistCollection
    {
        return new SavedWishlistCollection(
            $this->savedWishlistService->getSavedWishlists($request->user())
        );
    }

    public function saveWishlist(Request $request, string $id): SavedWishlistResource
    {
        return new SavedWishlistResource(
            $this->savedWishlistService->saveWishlist($request->user(), $id)
        );
    }

    public function removeSavedWishlist(Request $request, string $id): JsonResponse
    {
        $this->savedWishlistService->removeSavedWishlist($request->user(), $id);

        return response()->json([
            'success' => true,
            'statusCode' => 200,
            'data' => ['id' => $id],
        ]);
    }
}
