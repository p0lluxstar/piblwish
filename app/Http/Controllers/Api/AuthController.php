<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegistrationRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\VerifyRegistrationCodeRequest;
use App\Http\Resources\ApiResource;
use App\Http\Resources\Auth\RegistrationResource;
use App\Http\Resources\Auth\UserResource;
use App\Http\Resources\Auth\VerifyRegistrationCodeResource;
use App\Services\Auth\AuthService;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private AuthService $authService
    ) {}

    // Регистрация нового пользователя
    public function register(
        RegistrationRequest $request
    ): RegistrationResource {
        $user = $this->authService->register(
            $request->validated()
        );

        return new RegistrationResource($user);
    }

    // Подтверждение кода регистрации
    public function verifyRegistrationCode(
        VerifyRegistrationCodeRequest $request
    ): VerifyRegistrationCodeResource {
        $this->authService->verifyRegistrationCode(
            $request->validated()
        );

        return new VerifyRegistrationCodeResource(null);
    }

    // Вход пользователя
    public function login(
        LoginRequest $request
    ): UserResource {
        $user = $this->authService->login(
            $request->validated(),
            $request
        );

        return new UserResource($user);
    }

    // Запрос кода восстановления пароля.
    // Ответ одинаков для любого email, чтобы по нему нельзя было
    // определить, зарегистрирован ли адрес
    public function forgotPassword(
        ForgotPasswordRequest $request
    ): ApiResource {
        $this->authService->sendPasswordResetCode(
            $request->validated('email')
        );

        return new ApiResource([
            'message' => 'Если аккаунт с таким email существует, на него отправлен код восстановления',
        ]);
    }

    // Установка нового пароля по коду из письма
    public function resetPassword(
        ResetPasswordRequest $request
    ): ApiResource {
        $this->authService->resetPassword(
            $request->validated(),
            $request
        );

        return new ApiResource([
            'message' => 'Пароль изменён',
        ]);
    }

    // Выход пользователя
    public function logout(
        Request $request
    ): ApiResource {
        $this->authService->logout($request);

        return new ApiResource([
            'message' => 'Выход выполнен',
        ]);
    }
}
