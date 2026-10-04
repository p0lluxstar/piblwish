<?php

namespace App\Services\Wishlist;

use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class WishlistService
{
    public function getUserWishlists(User $user): Collection
    {
        return $user->wishlists()
            ->with('items')
            ->latest()
            ->get();
    }

    public function createWishlist(
        User $user,
        array $data
    ): Wishlist {
        return DB::transaction(function () use (
            $user,
            $data
        ) {
            // Если цвет и режим сюрприза не переданы, их задают значения по умолчанию в модели
            $wishlist = $user->wishlists()->create(
                $this->wishlistAttributes($data)
            );

            // Порядок позиций задаётся порядком массива items
            $wishlist->items()->createMany(
                collect($data['items'])
                    ->values()
                    ->map(fn($item, $index) => [
                        'description' => $item['label'],
                        'url' => $item['url'] ?? null,
                        'priority' => $item['priority'] ?? null,
                        'is_selected' => false,
                        'position' => $index,
                    ])
                    ->toArray()
            );

            return $wishlist->load('items');
        });
    }

    public function updateWishlist(User $user, string $id, array $data): Wishlist
    {
        return DB::transaction(function () use ($user, $id, $data) {
            $wishlist = Wishlist::query()
                ->where('user_id', $user->id)
                ->where('id', $id)
                ->firstOrFail();

            // Владелец не видел выбор гостей, если режим сюрприза был включён
            // до этого запроса: тогда isSelected из запроса не учитывается
            $selectionsHidden = $wishlist->hide_selections;

            // Обновляются только переданные поля
            $attributes = $this->wishlistAttributes($data);

            if ($attributes !== []) {
                $wishlist->update($attributes);
            }

            if (array_key_exists('items', $data)) {
                $this->syncItems($wishlist, $data['items'], $selectionsHidden);
            }

            return $wishlist->load('items');
        });
    }

    /**
     * Позиции изменяются на месте, а не пересоздаются: у них сохраняются id,
     * а с ними выбор гостей и брони, по которым гость может отменить выбор.
     *
     * Позиция с id существующей позиции списка обновляется, позиция без id
     * (или с чужим id) создаётся, позиции, которых нет в запросе, удаляются.
     * Порядок позиций задаётся порядком массива items.
     */
    private function syncItems(Wishlist $wishlist, array $items, bool $selectionsHidden): void
    {
        // Блокировка строк не даёт гостю отметить позицию, пока владелец её сохраняет
        $existing = $wishlist->items()->lockForUpdate()->get()->keyBy('id');
        $keptIds = [];

        // validated() собирает items в порядке правил: позиции с id (правило items.*.id)
        // идут раньше позиций без id. Исходный порядок восстанавливается по индексам
        ksort($items);

        foreach (array_values($items) as $index => $item) {
            $attributes = [
                'description' => $item['label'],
                'url' => $item['url'] ?? null,
                'priority' => $item['priority'] ?? null,
                'position' => $index,
            ];

            $isSelected = (bool) ($item['isSelected'] ?? false);
            $current = $existing->get((string) ($item['id'] ?? ''));

            // Повтор одного id в запросе создаёт новую позицию, а не перезаписывает ту же
            if ($current === null || in_array($current->id, $keptIds, true)) {
                // В режиме сюрприза владелец не видит выбор, поэтому новая позиция не выбрана
                $created = $wishlist->items()->create($attributes + [
                    'is_selected' => ! $selectionsHidden && $isSelected,
                ]);

                $keptIds[] = $created->id;

                continue;
            }

            // В режиме сюрприза isSelected из запроса не учитывается: владелец его не видел
            if (! $selectionsHidden) {
                $attributes['is_selected'] = $isSelected;

                // Владелец снял отметку: бронь гостя на эту позицию больше не действует
                if (! $isSelected) {
                    $attributes['reservation_id'] = null;
                }
            }

            $current->update($attributes);
            $keptIds[] = $current->id;
        }

        $removedIds = $existing->keys()->diff($keptIds);

        if ($removedIds->isNotEmpty()) {
            $wishlist->items()->whereIn('id', $removedIds)->delete();
        }
    }

    // Поля списка из запроса в атрибуты модели; непереданные поля не попадают в результат
    private function wishlistAttributes(array $data): array
    {
        $attributes = Arr::only($data, ['title', 'color']);

        if (array_key_exists('hideSelections', $data)) {
            $attributes['hide_selections'] = (bool) $data['hideSelections'];
        }

        return $attributes;
    }

    public function deleteWishlist(User $user, string $id): void
    {
        DB::transaction(function () use ($user, $id) {
            $wishlist = Wishlist::query()
                ->where('user_id', $user->id)
                ->where('id', $id)
                ->firstOrFail();
            $wishlist->delete();
        });
    }
}
