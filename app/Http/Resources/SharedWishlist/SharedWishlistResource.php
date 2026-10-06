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
            'title' => $this->title,
            'username' => $this->whenLoaded('user') ? $this->user->username : null,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(
                fn ($item) => (new WishlistItemResource($item))->withJointGift()
            )),
        ];

        // ApiResource::toResponse не отфильтровывает $this->when(), поэтому поле добавляется явно
        if ($this->reservation !== null) {
            $data['reservation'] = $this->reservation;
        }

        return $data;
    }
}
