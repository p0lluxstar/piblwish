<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Wishlist\WishlistCollection;
use Illuminate\Http\Request;
use App\Http\Resources\Wishlist\WishlistResource;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\Wishlist\ClearItemSelectionRequest;
use App\Http\Requests\Wishlist\CreateWishlistRequest;
use App\Services\Wishlist\WishlistService;
use Illuminate\Support\Facades\Log;
use App\Http\Requests\Wishlist\UpdateWishlistItemRequest;
use App\Http\Requests\Wishlist\UpdateWishlistRequest;

class WishlistController extends Controller
{
    public function __construct(
        private readonly WishlistService $wishlistService
    ) {}

    // Получить активные списки текущего пользователя или, с ?archived=1, его архив.
    // В meta.archivedCount — число списков в архиве
    public function getUserWishlists(
        Request $request
    ): WishlistCollection {
        $wishlists = $this->wishlistService
            ->getUserWishlists($request->user(), $request->boolean('archived'));

        return (new WishlistCollection($wishlists))->additional([
            'meta' => [
                'archivedCount' => $this->wishlistService->getArchivedCount($request->user()),
            ],
        ]);
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

    // Выбор гостей для окна редактирования и время, на которое он получен.
    // В режиме сюрприза id позиций отдаются только с ?reveal=1
    public function getSelectedItems(
        Request $request,
        string $id
    ): \Illuminate\Http\JsonResponse {
        $selections = $this->wishlistService->getSelections(
            $request->user(),
            $id,
            $request->boolean('reveal')
        );

        return response()->json([
            'success' => true,
            'statusCode' => 200,
            'data' => $selections,
        ]);
    }

    // Отметить дело в списке дел выполненным или снять отметку
    public function updateWishlistItem(
        UpdateWishlistItemRequest $request,
        string $id,
        string $itemId
    ): WishlistResource {
        $wishlist = $this->wishlistService->setItemSelected(
            $request->user(),
            $id,
            $itemId,
            $request->boolean('isSelected')
        );

        return new WishlistResource($wishlist);
    }

    // Снять выбор гостя с позиции списка желаний
    public function clearItemSelection(
        ClearItemSelectionRequest $request,
        string $id,
        string $itemId
    ): WishlistResource {
        $wishlist = $this->wishlistService->clearItemSelection(
            $request->user(),
            $id,
            $itemId,
            $request->date('checkedAt')
        );

        return new WishlistResource($wishlist);
    }

    // Перенести список в архив
    public function archiveWishlist(
        Request $request,
        string $id
    ): WishlistResource {
        $wishlist = $this->wishlistService->archiveWishlist(
            $request->user(),
            $id
        );

        return new WishlistResource($wishlist);
    }

    // Восстановить список из архива
    public function restoreWishlist(
        Request $request,
        string $id
    ): WishlistResource {
        $wishlist = $this->wishlistService->restoreWishlist(
            $request->user(),
            $id
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
