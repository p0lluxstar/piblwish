<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApiResource;
use App\Http\Resources\Auth\UserResource;
use App\Services\User\UserService;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(
        private readonly UserService $userService
    ) {}

    /**
     * Возвращает текущего авторизованного пользователя
     *
     * Требует middleware: auth:sanctum
     */

    // Получить текущего пользователя
    public function user(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    // Удалить аккаунт текущего пользователя
    public function deleteAccount(
        Request $request
    ): ApiResource {
        $this->userService->deleteAccount($request);

        return new ApiResource([
            'message' => 'Аккаунт удалён',
        ]);
    }
}
