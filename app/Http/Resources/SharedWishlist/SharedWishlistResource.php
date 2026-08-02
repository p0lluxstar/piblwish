<?php

namespace App\Http\Resources\SharedWishlist;

use App\Http\Resources\ApiResource;

use App\Http\Resources\Wishlist\WishlistItemResource;

class SharedWishlistResource extends ApiResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'username' => $this->whenLoaded('user') ? $this->user->username : null,
            'items' => WishlistItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
