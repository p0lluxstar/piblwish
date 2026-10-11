<?php

namespace App\Services\Wishlist;

use App\Enums\WishlistType;
use App\Models\User;
use App\Models\Wishlist;
use App\Models\WishlistJointGift;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WishlistService
{
    // Активные списки пользователя, новые первыми, или его архив ($archived),
    // где первыми идут списки, перенесённые в архив последними
    public function getUserWishlists(User $user, bool $archived = false): Collection
    {
        $query = $user->wishlists()->with('items');

        return $archived
            ? $query->archived()->latest('archived_at')->latest()->get()
            : $query->active()->latest()->get();
    }

    // Число списков в архиве: дашборд показывает его на ссылке в архив,
    // не загружая сами списки
    public function getArchivedCount(User $user): int
    {
        return $user->wishlists()->archived()->count();
    }

    /**
     * Перенести список в архив.
     *
     * Список становится доступен владельцу только для просмотра и перестаёт
     * открываться по ссылке. is_shared, брони и совместные подарки не меняются,
     * поэтому после восстановления список открывается гостям в прежнем виде.
     * Повторный перенос не меняет время переноса.
     */
    public function archiveWishlist(User $user, string $id): Wishlist
    {
        $wishlist = $this->findUserWishlist($user, $id);

        if (! $wishlist->isArchived()) {
            $wishlist->update(['archived_at' => now()]);
        }

        return $wishlist->load('items');
    }

    // Восстановить список из архива; для списка не в архиве ничего не меняется
    public function restoreWishlist(User $user, string $id): Wishlist
    {
        $wishlist = $this->findUserWishlist($user, $id);

        if ($wishlist->isArchived()) {
            $wishlist->update(['archived_at' => null]);
        }

        return $wishlist->load('items');
    }

    public function createWishlist(
        User $user,
        array $data
    ): Wishlist {
        return DB::transaction(function () use (
            $user,
            $data
        ) {
            // Если тип и цвет не переданы, их задают значения по умолчанию в модели
            $attributes = Arr::only($data, ['type', 'content']) + $this->wishlistAttributes($data);

            // Список желаний по умолчанию создаётся в режиме сюрприза: выбор гостей,
            // однажды увиденный владельцем, уже не скрыть. У списка дел и заметки
            // гости ничего не выбирают, для них остаётся значение из модели
            $type = WishlistType::tryFrom($data['type'] ?? '') ?? WishlistType::Gift;

            if ($type === WishlistType::Gift && ! array_key_exists('hide_selections', $attributes)) {
                $attributes['hide_selections'] = true;
            }

            $wishlist = $user->wishlists()->create($attributes);

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
            $wishlist = $this->findUserWishlist($user, $id);

            $this->ensureNotArchived($wishlist);

            // Обновляются только переданные поля
            $attributes = $this->wishlistAttributes($data);

            // У заметки изменяются только цвет и текст: названия, позиций,
            // режима сюрприза и даты у неё нет
            if ($wishlist->isNote()) {
                $attributes = Arr::only($attributes, ['color']) + Arr::only($data, ['content']);
            }

            // Выбор гостей в списке дел не скрывается: отметка гостя означает «выполнено»,
            // поэтому режим сюрприза для него не включается. В сборе гости ничего
            // не выбирают. Отметки гостей настраиваются только у списка дел
            if ($wishlist->isTodo() || $wishlist->isFund()) {
                unset($attributes['hide_selections']);
            }

            if (! $wishlist->isTodo()) {
                unset($attributes['guests_can_check'], $attributes['guest_name_required']);
            }

            if ($attributes !== []) {
                $wishlist->update($attributes);
            }

            if (array_key_exists('items', $data) && ! $wishlist->isNote()) {
                $this->syncItems($wishlist, $data['items']);
            }

            return $wishlist->load('items');
        });
    }

    /**
     * Выбор гостей для окна редактирования и время, на которое он получен.
     *
     * checkedAt окно передаёт при снятии выбора: сервер снимает только выбор,
     * сделанный раньше, то есть тот, который владелец мог видеть (clearItemSelection).
     * id выбранных позиций в режиме сюрприза отдаются, только если владелец
     * выключил режим в окне ($reveal), иначе ответ выбор не раскрывает.
     *
     * @return array{checkedAt: string, itemIds?: list<string>}
     */
    public function getSelections(User $user, string $id, bool $reveal): array
    {
        $wishlist = $this->findUserWishlist($user, $id);

        $this->ensureNotArchived($wishlist);

        // Время берётся до чтения выбора: позиция, выбранная между ними, попадёт
        // в ответ, но её бронь не раньше checkedAt, и сервер откажет в снятии,
        // а не снимет выбор, которого владелец не видел
        $result = ['checkedAt' => now()->toIso8601String()];

        if ($wishlist->hide_selections && ! $reveal) {
            return $result;
        }

        $result['itemIds'] = $wishlist->items()
            ->where('is_selected', true)
            ->pluck('id')
            ->all();

        return $result;
    }

    /**
     * Отметить дело выполненным или снять отметку без редактирования всего списка.
     * Только для списков дел: в списке желаний позиции выбирают гости, и отметка
     * владельца с карточки раскрыла бы или сбросила их выбор.
     */
    public function setItemSelected(User $user, string $id, string $itemId, bool $isSelected): Wishlist
    {
        return DB::transaction(function () use ($user, $id, $itemId, $isSelected) {
            $wishlist = $this->findUserWishlist($user, $id);

            $this->ensureNotArchived($wishlist);

            if (! $wishlist->isTodo()) {
                throw ValidationException::withMessages([
                    'isSelected' => 'Отмечать позиции с карточки можно только в списке дел',
                ]);
            }

            // Отметку ставит или снимает владелец: прежний автор отметки не сохраняется
            $wishlist->items()
                ->where('id', $itemId)
                ->firstOrFail()
                ->update([
                    'is_selected' => $isSelected,
                    'checked_by_guest' => false,
                    'checked_by_name' => null,
                ]);

            return $wishlist->load('items');
        });
    }

    /**
     * Снять выбор гостя с позиции списка желаний.
     *
     * Владелец не ставит отметки сам, а только снимает выбор гостя: например, если гость
     * передумал и потерял токен брони. Снятие выполняется отдельным запросом, а не при
     * сохранении списка, поэтому не зависит от несохранённой формы.
     *
     * $checkedAt — время, на которое окно получило выбор (getSelections). Бронь, созданная
     * не раньше него, означает, что позицию выбрали заново после открытия окна: владелец
     * этот выбор не видел, поэтому он не снимается (409). Ответ одинаков, была позиция
     * выбрана или нет: в режиме сюрприза владелец освобождает позицию, не узнавая этого.
     */
    public function clearItemSelection(User $user, string $id, string $itemId, CarbonInterface $checkedAt): Wishlist
    {
        return DB::transaction(function () use ($user, $id, $itemId, $checkedAt) {
            $wishlist = $this->findUserWishlist($user, $id);

            $this->ensureNotArchived($wishlist);

            // В списке дел отметка означает «выполнено» и меняется через setItemSelected
            if (! $wishlist->isGift()) {
                throw ValidationException::withMessages([
                    'isSelected' => 'Снять выбор гостя можно только в списке желаний',
                ]);
            }

            // Блокировка строки не даёт гостю изменить выбор, пока владелец его снимает
            $item = $wishlist->items()
                ->where('id', $itemId)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $item->is_selected) {
                return $wishlist->load('items');
            }

            // Отметка без брони (поставлена владельцем до того, как это запретили)
            // снимается всегда. created_at хранится с точностью до секунды, поэтому
            // сравнение строгое: при совпадении секунды выбор гостя не снимается
            $reservedAt = $item->reservation?->created_at;

            if ($reservedAt !== null && $reservedAt->greaterThanOrEqualTo($checkedAt)) {
                abort(409, 'Состояние позиции изменилось после открытия окна');
            }

            $item->update([
                'is_selected' => false,
                'reservation_id' => null,
            ]);

            // Выбор снят: совместный подарок на позицию больше не действует
            WishlistJointGift::query()->where('item_id', $item->id)->delete();

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
     *
     * isSelected из запроса учитывается только в списке дел. В списке желаний
     * отметки ставят гости, а владелец снимает их через clearItemSelection
     */
    private function syncItems(Wishlist $wishlist, array $items): void
    {
        // Блокировка строк не даёт гостю отметить позицию, пока владелец её сохраняет
        $existing = $wishlist->items()->lockForUpdate()->get()->keyBy('id');
        $keptIds = [];
        $isTodo = $wishlist->isTodo();

        // validated() собирает items в порядке правил: позиции с id (правило items.*.id)
        // идут раньше позиций без id. Исходный порядок восстанавливается по индексам
        ksort($items);

        foreach (array_values($items) as $index => $item) {
            $attributes = $this->itemAttributes($wishlist, $item, $index);

            $current = $existing->get((string) ($item['id'] ?? ''));

            if ($isTodo) {
                $attributes['is_selected'] = (bool) ($item['isSelected'] ?? false);

                // Автор отметки сохраняется, только если дело осталось выполненным.
                // Отметку, которую владелец поставил или снял в форме, он делает сам
                if (! $attributes['is_selected'] || ! $current?->is_selected) {
                    $attributes['checked_by_guest'] = false;
                    $attributes['checked_by_name'] = null;
                }
            }

            // Повтор одного id в запросе создаёт новую позицию, а не перезаписывает ту же
            if ($current === null || in_array($current->id, $keptIds, true)) {
                $created = $wishlist->items()->create($attributes + ['is_selected' => false]);

                $keptIds[] = $created->id;

                continue;
            }

            $current->update($attributes);
            $keptIds[] = $current->id;
        }

        $removedIds = $existing->keys()->diff($keptIds);

        if ($removedIds->isNotEmpty()) {
            $wishlist->items()->whereIn('id', $removedIds)->delete();
        }
    }

    // Поля позиции из запроса в атрибуты модели. У дел нет ссылок, приоритета
    // и стоимости, у целей сбора — приоритета: они не сохраняются, даже если
    // переданы в запросе на изменение. price у цели сбора — целевая сумма
    private function itemAttributes(Wishlist $wishlist, array $item, int $position): array
    {
        $isTodo = $wishlist->isTodo();

        return [
            'description' => $item['label'],
            'urls' => $isTodo ? null : $this->itemUrls($item['urls'] ?? null),
            'priority' => $isTodo || $wishlist->isFund() ? null : ($item['priority'] ?? null),
            'price' => $isTodo ? null : ($item['price'] ?? null),
            'position' => $position,
        ];
    }

    // Ссылки позиции без пустых элементов; null, если не осталось ни одной
    private function itemUrls(?array $urls): ?array
    {
        $urls = array_values(array_filter($urls ?? [], fn ($url) => $url !== null && $url !== ''));

        return $urls === [] ? null : $urls;
    }

    // Поля списка из запроса в атрибуты модели; непереданные поля не попадают в результат
    private function wishlistAttributes(array $data): array
    {
        $attributes = Arr::only($data, ['title', 'color']);

        if (array_key_exists('hideSelections', $data)) {
            $attributes['hide_selections'] = (bool) $data['hideSelections'];
        }

        if (array_key_exists('isShared', $data)) {
            $attributes['is_shared'] = (bool) $data['isShared'];
        }

        if (array_key_exists('guestsCanCheck', $data)) {
            $attributes['guests_can_check'] = (bool) $data['guestsCanCheck'];
        }

        if (array_key_exists('guestNameRequired', $data)) {
            $attributes['guest_name_required'] = (bool) $data['guestNameRequired'];
        }

        // null убирает дату
        if (array_key_exists('dueDate', $data)) {
            $attributes['due_date'] = $data['dueDate'];
        }

        return $attributes;
    }

    // Архивный список можно удалить
    public function deleteWishlist(User $user, string $id): void
    {
        DB::transaction(function () use ($user, $id) {
            $wishlist = $this->findUserWishlist($user, $id);
            $wishlist->delete();
        });
    }

    /**
     * Удалить списки из архива пользователя.
     *
     * Удаляются только переданные списки, которые принадлежат пользователю
     * и всё ещё находятся в архиве: список, перенесённый в архив или
     * восстановленный уже после подтверждения, не затрагивается.
     * Возвращает число удалённых списков.
     */
    public function deleteArchivedWishlists(User $user, array $ids): int
    {
        return DB::transaction(
            fn () => $user->wishlists()->archived()->whereIn('id', $ids)->delete()
        );
    }

    // Список пользователя; чужой или несуществующий — 404
    private function findUserWishlist(User $user, string $id): Wishlist
    {
        return Wishlist::query()
            ->where('user_id', $user->id)
            ->where('id', $id)
            ->firstOrFail();
    }

    // Архивный список доступен только для просмотра: изменить его,
    // отметить дело или снять выбор гостя можно после восстановления
    private function ensureNotArchived(Wishlist $wishlist): void
    {
        if ($wishlist->isArchived()) {
            abort(409, 'Список находится в архиве. Восстановите его, чтобы изменить');
        }
    }
}
