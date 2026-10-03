<?php

namespace App\Http\Resources\Wishlist;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\ApiResource;

class WishlistResource extends ApiResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            // Ключ цвета фона списка (white, lavender, …), см. App\Enums\WishlistColor
            'color' => $this->color->value,
            // Дата создания списка
            'createdAt' => $this->created_at?->toIso8601String(),
            'items' => WishlistItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
