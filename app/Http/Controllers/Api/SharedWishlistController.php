<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SharedWishlist\CancelReservationRequest;
use App\Http\Requests\SharedWishlist\GetReservationsRequest;
use App\Http\Requests\SharedWishlist\UpdateSharedWishlistItemsRequest;
use App\Http\Resources\SharedWishlist\SharedWishlistResource;
use App\Services\SharedWishlist\SharedWishlistService;
use Illuminate\Http\JsonResponse;
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
        Log::info('Wishlist ID:', [
            'id' => $id,
        ]);

        $wishlist = $this->sharedWishlistService
            ->getWishlistById(
                $id
            );

        return new SharedWishlistResource($wishlist);
    }

    // Обновить выбранные элементы в списке желаний; в ответе токен брони для отмены выбора
    public function updateSharedWishlistItems(
        UpdateSharedWishlistItemsRequest $request,
        string $wishlistId
    ): SharedWishlistResource {

        Log::info('Обновление элементов списка желаний', [
            'wishlist_id' => $wishlistId
        ]);

        $result = $this->sharedWishlistService->updateSharedWishlistItems(
            $wishlistId,
            $request->validated()['item_ids']
        );

        return (new SharedWishlistResource($result['wishlist']))
            ->withReservation($result['reservation']);
    }

    // Позиции броней гостя по токенам из его браузера или из ссылки для отмены
    public function getReservations(
        GetReservationsRequest $request,
        string $wishlistId
    ): JsonResponse {
        $reservations = $this->sharedWishlistService->getReservations(
            $wishlistId,
            $request->validated()['tokens']
        );

        return response()->json([
            'success' => true,
            'statusCode' => 200,
            'data' => $reservations,
        ]);
    }

    // Отменить выбор позиций брони
    public function cancelReservation(
        CancelReservationRequest $request,
        string $wishlistId
    ): SharedWishlistResource {
        $validated = $request->validated();

        $result = $this->sharedWishlistService->cancelReservation(
            $wishlistId,
            $validated['token'],
            $validated['item_ids']
        );

        return (new SharedWishlistResource($result['wishlist']))
            ->withReservation($result['reservation']);
    }
}
