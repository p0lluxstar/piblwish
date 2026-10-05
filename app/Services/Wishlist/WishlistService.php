<?php

namespace App\Services\Wishlist;

use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
            // Если тип, цвет и режим сюрприза не переданы, их задают значения по умолчанию в модели
            $wishlist = $user->wishlists()->create(
                Arr::only($data, ['type', 'content']) + $this->wishlistAttributes($data)
            );

            // У заметки нет позиций: её текст уже сохранён в content
            if ($wishlist->isNote()) {
                return $wishlist->load('items');
            }

            // Порядок позиций задаётся порядком массива items
            $wishlist->items()->createMany(
                collect($data['items'])
                    ->values()
                    ->map(fn ($item, $index) => $this->itemAttributes($wishlist, $item, $index) + [
                        'is_selected' => false,
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

            // У заметки изменяются только цвет и текст: названия, позиций
            // и режима сюрприза у неё нет
            if ($wishlist->isNote()) {
                $attributes = Arr::only($attributes, ['color']) + Arr::only($data, ['content']);
            }

            // У списка дел нет гостей, поэтому режим сюрприза для него не включается
            if ($wishlist->isTodo()) {
                unset($attributes['hide_selections']);
            }

            if ($attributes !== []) {
                $wishlist->update($attributes);
            }

            if (array_key_exists('items', $data) && ! $wishlist->isNote()) {
                $this->syncItems($wishlist, $data['items'], $selectionsHidden);
            }

            return $wishlist->load('items');
        });
    }

    /**
     * Отметить дело выполненным или снять отметку без редактирования всего списка.
     * Только для списков дел: в списке желаний позиции выбирают гости, и отметка
     * владельца с карточки раскрыла бы или сбросила их выбор.
     */
    public function setItemSelected(User $user, string $id, string $itemId, bool $isSelected): Wishlist
    {
        return DB::transaction(function () use ($user, $id, $itemId, $isSelected) {
            $wishlist = Wishlist::query()
                ->where('user_id', $user->id)
                ->where('id', $id)
                ->firstOrFail();

            if (! $wishlist->isTodo()) {
                throw ValidationException::withMessages([
                    'isSelected' => 'Отмечать позиции с карточки можно только в списке дел',
                ]);
            }

            $wishlist->items()
                ->where('id', $itemId)
                ->firstOrFail()
                ->update(['is_selected' => $isSelected]);

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
            $attributes = $this->itemAttributes($wishlist, $item, $index);

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

    // Поля позиции из запроса в атрибуты модели. У дел нет ссылки, приоритета
    // и стоимости: они не сохраняются, даже если переданы в запросе на изменение
    private function itemAttributes(Wishlist $wishlist, array $item, int $position): array
    {
        $isTodo = $wishlist->isTodo();

        return [
            'description' => $item['label'],
            'url' => $isTodo ? null : ($item['url'] ?? null),
            'priority' => $isTodo ? null : ($item['priority'] ?? null),
            'price' => $isTodo ? null : ($item['price'] ?? null),
            'position' => $position,
        ];
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
