<?php

namespace App\Http\Resources\Wishlist;

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

    // Кто отметил дело: только у позиций списка дел. В списке желаний позиции
    // выбирают гости через брони, и это поле там не нужно
    private bool $withCheckedBy = false;

    public function withCheckedBy(bool $with = true): static
    {
        $this->withCheckedBy = $with;

        return $this;
    }

    public function toArray($request): array
    {
        $data = [
            'id' => $this->id,
            'isSelected' => $this->when(! $this->hideSelection, $this->is_selected),
            'label' => $this->description,
            // Ссылки на товар (до трёх); пустой массив, если их нет
            'urls' => $this->urls ?? [],
            // Приоритет 1–3 или null; в отличие от isSelected, виден и в режиме сюрприза
            'priority' => $this->priority?->value,
            // Стоимость в целых рублях или null; как и приоритет, видна в режиме сюрприза
            'price' => $this->price,
        ];

        // Автор отметки выполненного дела: { guest: false, name: null } — владелец,
        // { guest: true, name } — гость по ссылке, name = null, если он не указал имя.
        // У невыполненного дела — null
        if ($this->withCheckedBy) {
            $data['checkedBy'] = ! $this->is_selected ? null : [
                'guest' => $this->checked_by_guest,
                'name' => $this->checked_by_name,
            ];
        }

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
