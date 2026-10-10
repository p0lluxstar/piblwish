<?php

namespace App\Http\Resources\SharedWishlist;

use App\Http\Resources\ApiResource;

use App\Http\Resources\Wishlist\WishlistItemResource;

class SharedWishlistResource extends ApiResource
{
    // Бронь гостя после сохранения или отмены выбора: { token, itemIds }
    private ?array $reservation = null;

    public function withReservation(array $reservation): static
    {
        $this->reservation = $reservation;

        return $this;
    }

    public function toArray($request): array
    {
        $data = [
            'id' => $this->id,
            // gift — список желаний; todo — список дел, открытый владельцем только
            // для просмотра: у его позиций isSelected означает «выполнено»
            'type' => $this->type->value,
            'title' => $this->title,
            // Цвет карточки, как у владельца в дашборде
            'color' => $this->color->value,
            // Может ли гость отмечать дела выполненными; у списка желаний false
            'guestsCanCheck' => $this->guestsCanCheck(),
            // Должен ли гость указать имя, отмечая дела; false, если отмечать нельзя
            'guestNameRequired' => $this->guestsCanCheck() && $this->guest_name_required,
            // Срок списка дел или дата события списка желаний (Y-m-d) либо null
            'dueDate' => $this->due_date?->toDateString(),
            'username' => $this->whenLoaded('user') ? $this->user->username : null,
            // Фотография владельца или null — тогда показывается первая буква имени
            'avatarUrl' => $this->relationLoaded('user') ? $this->user->avatarUrl() : null,
            // Совместный подарок бывает только у позиций списка желаний
            'items' => $this->whenLoaded('items', fn () => $this->items->map(
                fn ($item) => (new WishlistItemResource($item))
                    ->withJointGift($this->isGift())
                    ->withCheckedBy($this->isTodo())
            )),
        ];

        // ApiResource::toResponse не отфильтровывает $this->when(), поэтому поле добавляется явно
        if ($this->reservation !== null) {
            $data['reservation'] = $this->reservation;
        }

        return $data;
    }
}
