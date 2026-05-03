<?php

namespace App\Http\Resources\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VerifyRegistrationCodeResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'message' => 'Email успешно подтвержден.',
        ];
    }
}
