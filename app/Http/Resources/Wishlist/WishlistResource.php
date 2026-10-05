<?php

namespace App\Http\Resources\Wishlist;

use App\Http\Resources\ApiResource;

class WishlistResource extends ApiResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            // Тип списка: gift — список желаний, todo — список дел, note — заметка,
            // см. App\Enums\WishlistType
            'type' => $this->type->value,
            // У заметки названия нет: null
            'title' => $this->title,
            // Текст заметки; у списков желаний и дел — null
            'content' => $this->content,
            // Ключ цвета фона списка (white, lavender, …), см. App\Enums\WishlistColor
            'color' => $this->color->value,
            // Дата создания списка
            'createdAt' => $this->created_at?->toIso8601String(),
            // Режим сюрприза: при включённом флаге у позиций нет поля isSelected
            'hideSelections' => $this->hide_selections,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(
                fn ($item) => (new WishlistItemResource($item))
                    ->hideSelection($this->hide_selections)
            )),
        ];
    }
}
