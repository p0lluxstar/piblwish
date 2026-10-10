<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Enums\AppBackground;
use App\Http\Requests\User\ChangePasswordRequest;
use App\Http\Requests\User\ConfirmEmailChangeRequest;
use App\Http\Requests\User\RequestEmailChangeRequest;
use App\Http\Requests\User\UpdateAvatarRequest;
use App\Http\Requests\User\UpdateUserRequest;
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

    // Изменить настройки текущего пользователя: фон приложения
    // и показ строки «Скоро у друзей»; непереданная настройка не меняется
    public function updateUser(UpdateUserRequest $request): UserResource
    {
        $background = $request->validated('background');
        $showFriendsEvents = $request->validated('showFriendsEvents');

        $user = $this->userService->updateSettings(
            $request->user(),
            $background === null ? null : AppBackground::from($background),
            $showFriendsEvents === null ? null : (bool) $showFriendsEvents
        );

        return new UserResource($user);
    }

    // Загрузить фотографию пользователя; прежняя фотография удаляется
    public function updateAvatar(UpdateAvatarRequest $request): UserResource
    {
        $user = $this->userService->updateAvatar(
            $request->user(),
            $request->file('avatar')
        );

        return new UserResource($user);
    }

    // Удалить фотографию пользователя: снова показывается первая буква имени
    public function deleteAvatar(Request $request): UserResource
    {
        $user = $this->userService->deleteAvatar($request->user());

        return new UserResource($user);
    }

    // Сменить пароль текущего пользователя
    public function changePassword(
        ChangePasswordRequest $request
    ): ApiResource {
        $this->userService->changePassword(
            $request,
            $request->validated('password')
        );

        return new ApiResource([
            'message' => 'Пароль изменён',
        ]);
    }

    // Запросить смену email: код уходит на новый адрес
    public function requestEmailChange(
        RequestEmailChangeRequest $request
    ): ApiResource {
        $this->userService->requestEmailChange(
            $request->user(),
            $request->validated('email')
        );

        return new ApiResource([
            'message' => 'Код отправлен на новый email',
        ]);
    }

    // Подтвердить новый email кодом из письма
    public function confirmEmailChange(
        ConfirmEmailChangeRequest $request
    ): UserResource {
        $user = $this->userService->confirmEmailChange(
            $request,
            $request->validated('code')
        );

        return new UserResource($user);
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
