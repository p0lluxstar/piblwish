<?php

namespace App\Http\Resources\Wishlist;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WishlistItemResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->description,
        ];
    }
}
