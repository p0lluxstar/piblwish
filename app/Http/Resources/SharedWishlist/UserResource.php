<?php

namespace App\Http\Resources\SharedWishlist;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'username' => $this->username,
        ];
    }
}
