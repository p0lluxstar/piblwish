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
            // Открывается ли список по общей ссылке: у списка желаний всегда true,
            // у заметки false, у списка дел — если владелец включил доступ
            'isShared' => $this->isShared(),
            // Разрешено ли гостям отмечать дела по ссылке; у списка желаний и заметки false.
            // Сохранённое значение, а не действующее: при выключенном доступе по ссылке
            // окно редактирования показывает выбор владельца
            'guestsCanCheck' => $this->isTodo() && $this->guests_can_check,
            // Должен ли гость указать имя, отмечая дела; у списка желаний и заметки false
            'guestNameRequired' => $this->isTodo() && $this->guest_name_required,
            // Срок списка дел или дата события списка желаний (Y-m-d) либо null;
            // у заметки всегда null
            'dueDate' => $this->due_date?->toDateString(),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(
                fn ($item) => (new WishlistItemResource($item))
                    ->hideSelection($this->hide_selections)
                    ->withCheckedBy($this->isTodo())
            )),
        ];
    }
}
