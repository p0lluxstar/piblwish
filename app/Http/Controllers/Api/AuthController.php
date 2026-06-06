<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegistrationRequest;
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


    public function register(
        RegistrationRequest $request
    ): RegistrationResource {
        $user = $this->authService->register(
            $request->validated()
        );

        return new RegistrationResource($user);
    }

    public function verifyRegistrationCode(
        VerifyRegistrationCodeRequest $request
    ): VerifyRegistrationCodeResource {
        $this->authService->verifyRegistrationCode(
            $request->validated()
        );

        return new VerifyRegistrationCodeResource(null);
    }

    public function login(
        LoginRequest $request
    ): UserResource {
        $user = $this->authService->login(
            $request->validated(),
            $request
        );

        return new UserResource($user);
    }

    public function logout(
        Request $request
    ): ApiResource {
        $this->authService->logout($request);

        return new ApiResource([
            'message' => 'Выход выполнен',
        ]);
    }
}
