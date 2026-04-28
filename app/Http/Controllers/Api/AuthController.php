<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;
use App\Http\Resources\Auth\UserResource;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\Auth\RegistrationResource;
use App\Http\Requests\Auth\RegistrationRequest;

class AuthController extends Controller
{
    public function register(RegistrationRequest $request)
    {
        $user = User::create([
            'username' => $request->username,
            'email' => $request->email,
            'password' => $request->password,
        ]);

        $code = random_int(100000, 999999);

        $user->registrationVerificationCodes()->create([
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(2),
        ]);

        // Тут дальше обычно:
        // 1. генерируется код подтверждения
        // 2. код отправляется пользователю
        // 3. пользователь НЕ логинится до подтверждения

        return RegistrationResource::make($user);
    }

    public function login(LoginRequest $request): UserResource
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::guard('web')->attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => ['Неверный email или пароль.'],
            ]);
        }

        $request->session()->regenerate();

        return UserResource::make($request->user());
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'message' => 'Выход выполнен',
        ]);
    }
}
