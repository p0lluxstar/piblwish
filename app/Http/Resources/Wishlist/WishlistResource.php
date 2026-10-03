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
            'items' => WishlistItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
