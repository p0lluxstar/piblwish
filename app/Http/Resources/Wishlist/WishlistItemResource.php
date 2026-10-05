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

    public function toArray($request): array
    {
        return [
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
    }
}
