<?php

namespace App\Services\SharedWishlist;

use App\Models\Wishlist;
use App\Models\WishlistItem;
use App\Models\WishlistReservation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SharedWishlistService
{
    // Длина токена брони: 40 символов из [A-Za-z0-9], перебор невозможен
    public const RESERVATION_TOKEN_LENGTH = 40;

    // Получить конкретный вишлист по ID
    public function getWishlistById(
        string $id
    ): Wishlist {
        return Wishlist::query()
            ->with(['items', 'user'])
            ->findOrFail($id);
    }

    /**
     * Отметить позиции как выбранные и создать бронь гостя.
     *
     * Возвращает список и бронь: token отдаётся гостю один раз,
     * в БД хранится только его хеш.
     *
     * @return array{wishlist: Wishlist, reservation: array{token: string, itemIds: list<string>}}
     */
    public function updateSharedWishlistItems(
        string $wishlistId,
        array $itemIds
    ): array {
        $wishlist = Wishlist::query()->findOrFail($wishlistId);
        $itemIds = array_values(array_unique($itemIds));
        $token = Str::random(self::RESERVATION_TOKEN_LENGTH);

        DB::transaction(function () use ($wishlist, $itemIds, $token) {
            $reservation = $wishlist->reservations()->create([
                'token_hash' => WishlistReservation::hashToken($token),
            ]);

            foreach ($itemIds as $itemId) {

                $updated = WishlistItem::query()
                    ->where('wishlist_id', $wishlist->id)
                    ->where('id', $itemId)
                    ->where('is_selected', false)
                    ->update([
                        'is_selected' => true,
                        'reservation_id' => $reservation->id,
                    ]);

                if ($updated === 0) {
                    throw ValidationException::withMessages([
                        'items' => 'One or more items have already been selected.',
                    ]);
                }
            }
        });

        return [
            'wishlist' => $this->freshWishlist($wishlist->id),
            'reservation' => ['token' => $token, 'itemIds' => $itemIds],
        ];
    }

    /**
     * Какие позиции входят в брони с переданными токенами.
     *
     * Неизвестные токены и брони без позиций в ответ не попадают:
     * гость удаляет их из своего браузера.
     *
     * @return list<array{token: string, itemIds: list<string>}>
     */
    public function getReservations(string $wishlistId, array $tokens): array
    {
        Wishlist::query()->findOrFail($wishlistId);

        $hashes = collect($tokens)
            ->unique()
            ->mapWithKeys(fn (string $token) => [WishlistReservation::hashToken($token) => $token]);

        return WishlistReservation::query()
            ->where('wishlist_id', $wishlistId)
            ->whereIn('token_hash', $hashes->keys())
            ->with('items:id,reservation_id')
            ->get()
            ->filter(fn (WishlistReservation $reservation) => $reservation->items->isNotEmpty())
            ->map(fn (WishlistReservation $reservation) => [
                'token' => $hashes[$reservation->token_hash],
                'itemIds' => $reservation->items->pluck('id')->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * Отменить выбор части позиций брони; остальные позиции остаются за гостем.
     * Бронь без позиций удаляется.
     *
     * @return array{wishlist: Wishlist, reservation: array{token: string, itemIds: list<string>}}
     */
    public function cancelReservation(string $wishlistId, string $token, array $itemIds): array
    {
        $itemIds = array_values(array_unique($itemIds));

        $remainingIds = DB::transaction(function () use ($wishlistId, $token, $itemIds) {
            $reservation = WishlistReservation::query()
                ->where('wishlist_id', $wishlistId)
                ->where('token_hash', WishlistReservation::hashToken($token))
                ->lockForUpdate()
                ->first();

            if ($reservation === null) {
                abort(404, 'Бронь не найдена');
            }

            $items = $reservation->items()
                ->whereIn('id', $itemIds)
                ->lockForUpdate()
                ->get();

            // Отменить можно только свои позиции: чужая или удалённая позиция отклоняет весь запрос
            if ($items->count() !== count($itemIds)) {
                throw ValidationException::withMessages([
                    'item_ids' => 'Некоторые подарки не входят в вашу бронь',
                ]);
            }

            WishlistItem::query()
                ->whereIn('id', $itemIds)
                ->update([
                    'is_selected' => false,
                    'reservation_id' => null,
                ]);

            $remainingIds = $reservation->items()->pluck('id')->all();

            if ($remainingIds === []) {
                $reservation->delete();
            }

            return $remainingIds;
        });

        return [
            'wishlist' => $this->freshWishlist($wishlistId),
            'reservation' => ['token' => $token, 'itemIds' => $remainingIds],
        ];
    }

    private function freshWishlist(string $wishlistId): Wishlist
    {
        return Wishlist::with(['items', 'user'])->findOrFail($wishlistId);
    }
}
