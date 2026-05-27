<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Resources\Auth\UserResource;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    /**
     * Возвращает текущего авторизованного пользователя
     *
     * Требует middleware: auth:sanctum
     */
    public function user(Request $request): UserResource
    {
        return new UserResource($request->user());
    }
}
