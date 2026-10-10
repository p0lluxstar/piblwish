<?php

namespace App\Http\Resources\Auth;

use App\Enums\AppBackground;
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
            // Ключ фона приложения из App\Enums\AppBackground. У модели, созданной
            // в этом запросе и не перечитанной из БД, атрибута ещё нет
            'background' => ($this->background ?? AppBackground::Blossom)->value,
            // Адрес фотографии или null — тогда показывается первая буква имени
            'avatarUrl' => $this->avatarUrl(),
            // Дата регистрации, показывается в окне настроек аккаунта
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
