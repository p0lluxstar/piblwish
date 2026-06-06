<?php

namespace App\Http\Resources\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RegistrationResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'message' => 'Пользователь создан. Подтвердите регистрацию через код.',
        ];
    }
}
