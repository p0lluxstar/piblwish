<?php

namespace App\Services\SharedWishlist;

use App\Enums\WishlistType;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use App\Models\WishlistJointGift;
use App\Models\WishlistReservation;
use Illuminate\Database\Eloquent\Builder;
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
        return $this->sharedQuery()
            ->with(['items.jointGift', 'user'])
            ->findOrFail($id);
    }

    /**
     * Отметить позиции как выбранные и создать бронь гостя.
     *
     * Возвращает список и бронь: token отдаётся гостю один раз,
     * в БД хранится только его хеш.
     *
     * $jointGifts — совместные подарки для части выбранных позиций:
     * [{ item_id, name, contact?, comment? }]. Они привязываются к той же брони,
     * поэтому отмена выбора позиции удаляет и совместный подарок.
     *
     * @return array{wishlist: Wishlist, reservation: array{token: string, itemIds: list<string>}}
     */
    public function updateSharedWishlistItems(
        string $wishlistId,
        array $itemIds,
        array $jointGifts = []
    ): array {
        $wishlist = $this->sharedQuery()->findOrFail($wishlistId);
        $itemIds = array_values(array_unique($itemIds));
        $token = Str::random(self::RESERVATION_TOKEN_LENGTH);

        DB::transaction(function () use ($wishlist, $itemIds, $jointGifts, $token) {
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

            // Запрос проверил, что каждая позиция совместного подарка выбирается в нём же,
            // а позиция, выбранная выше, принадлежит этому списку
            foreach ($jointGifts as $jointGift) {
                WishlistJointGift::create([
                    'item_id' => $jointGift['item_id'],
                    'reservation_id' => $reservation->id,
                    'organizer_name' => $jointGift['name'],
                    'contact' => $jointGift['contact'] ?? null,
                    'comment' => $jointGift['comment'] ?? null,
                ]);
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
        $this->sharedQuery()->findOrFail($wishlistId);

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
        $this->sharedQuery()->findOrFail($wishlistId);

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

            // Гость отказался от позиции: совместный подарок на неё тоже отменяется
            WishlistJointGift::query()->whereIn('item_id', $itemIds)->delete();

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

    /**
     * Изменить, добавить или убрать совместный подарок на позицию своей брони.
     * Выбор позиции при этом не меняется.
     *
     * $jointGift — { name, contact?, comment? } или null, чтобы убрать совместный подарок.
     */
    public function updateJointGift(string $wishlistId, string $itemId, string $token, ?array $jointGift): Wishlist
    {
        $this->sharedQuery()->findOrFail($wishlistId);

        DB::transaction(function () use ($wishlistId, $itemId, $token, $jointGift) {
            $reservation = WishlistReservation::query()
                ->where('wishlist_id', $wishlistId)
                ->where('token_hash', WishlistReservation::hashToken($token))
                ->lockForUpdate()
                ->first();

            if ($reservation === null) {
                abort(404, 'Бронь не найдена');
            }

            // Изменить можно только совместный подарок на свою позицию
            if (! $reservation->items()->where('id', $itemId)->lockForUpdate()->exists()) {
                throw ValidationException::withMessages([
                    'item' => 'Подарок не входит в вашу бронь',
                ]);
            }

            if ($jointGift === null) {
                WishlistJointGift::query()->where('item_id', $itemId)->delete();

                return;
            }

            WishlistJointGift::query()->updateOrCreate(
                ['item_id' => $itemId],
                [
                    'reservation_id' => $reservation->id,
                    'organizer_name' => $jointGift['name'],
                    'contact' => $jointGift['contact'] ?? null,
                    'comment' => $jointGift['comment'] ?? null,
                ]
            );
        });

        return $this->freshWishlist($wishlistId);
    }

    private function freshWishlist(string $wishlistId): Wishlist
    {
        return $this->sharedQuery()->with(['items.jointGift', 'user'])->findOrFail($wishlistId);
    }

    // По ссылке доступны только списки желаний: список дел и заметку видит лишь владелец,
    // поэтому для гостя он не существует и все публичные эндпоинты отвечают 404
    private function sharedQuery(): Builder
    {
        return Wishlist::query()->where('type', WishlistType::Gift);
    }
}
