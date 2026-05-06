<?php

namespace App\Http\Resources\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\ApiResource;

class UserResource extends ApiResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'email' => $this->email,
        ];
    }
}
