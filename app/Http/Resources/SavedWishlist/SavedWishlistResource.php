<?php

namespace App\Http\Resources\SavedWishlist;

use App\Http\Resources\ApiResource;

/**
 * Чужой список в разделе «Чужие списки»: сводка для карточки.
 *
 * Отдаётся не больше, чем гость видит на общей странице. Если список
 * больше не открывается по ссылке (владелец закрыл доступ к списку
 * или перенёс его в архив),
 * available = false и содержимое списка не отдаётся: только владелец
 * и дата добавления, чтобы закладку можно было узнать и убрать.
 */
class SavedWishlistResource extends ApiResource
{
    public function toArray($request): array
    {
        $wishlist = $this->wishlist;
        $available = $wishlist->isOpenByLink();

        return [
            // id самого списка: по нему открывается общая страница и убирается закладка
            'id' => $wishlist->id,
            'available' => $available,
            'savedAt' => $this->created_at?->toIso8601String(),
            'username' => $wishlist->user?->username,
            'avatarUrl' => $wishlist->user?->avatarUrl(),
            // gift — список желаний, todo — список дел
            'type' => $available ? $wishlist->type->value : null,
            'title' => $available ? $wishlist->title : null,
            'color' => $available ? $wishlist->color->value : null,
            'dueDate' => $available ? $wishlist->due_date?->toDateString() : null,
            'itemsCount' => $available ? $wishlist->items_count : null,
            // Выбранные гостями подарки или выполненные дела: их видно и на общей странице
            'selectedCount' => $available ? $wishlist->selected_items_count : null,
        ];
    }
}
