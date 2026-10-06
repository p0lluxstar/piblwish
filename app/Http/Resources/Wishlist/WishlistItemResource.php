<?php

namespace App\Http\Resources\Wishlist;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WishlistItemResource extends JsonResource
{
    // Режим сюрприза: владелец списка не должен знать, какие позиции выбраны
    private bool $hideSelection = false;

    public function hideSelection(bool $hide = true): static
    {
        $this->hideSelection = $hide;

        return $this;
    }

    // Совместный подарок выводится только гостям: имя и контакт организатора
    // предназначены им, владелец списка не видит их ни в каком режиме
    private bool $withJointGift = false;

    public function withJointGift(bool $with = true): static
    {
        $this->withJointGift = $with;

        return $this;
    }

    public function toArray($request): array
    {
        $data = [
            'id' => $this->id,
            'isSelected' => $this->when(! $this->hideSelection, $this->is_selected),
            'label' => $this->description,
            // Ссылка на товар или null
            'url' => $this->url,
            // Приоритет 1–3 или null; в отличие от isSelected, виден и в режиме сюрприза
            'priority' => $this->priority?->value,
            // Стоимость в целых рублях или null; как и приоритет, видна в режиме сюрприза
            'price' => $this->price,
        ];

        // ApiResource::toResponse не отфильтровывает $this->when(), поэтому поле добавляется явно
        if ($this->withJointGift) {
            $jointGift = $this->jointGift;

            $data['jointGift'] = $jointGift === null ? null : [
                'name' => $jointGift->organizer_name,
                'contact' => $jointGift->contact,
                'comment' => $jointGift->comment,
            ];
        }

        return $data;
    }
}
