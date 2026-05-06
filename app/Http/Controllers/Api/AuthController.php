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
use App\Http\Resources\Auth\VerifyRegistrationCodeResource;
use App\Http\Requests\Auth\RegistrationRequest;
use App\Http\Requests\Auth\VerifyRegistrationCodeRequest;
use Illuminate\Support\Facades\Log;
use App\Http\Resources\ApiResource;

class AuthController extends Controller
{
    public function register(RegistrationRequest $request): RegistrationResource
    {
        $user = User::create([
            'username' => $request->username,
            'email' => $request->email,
            'password' => $request->password,
        ]);

        $code = random_int(100000, 999999);

        // Записываем в лог
        Log::info("Код регистрации для {$user->email}: {$code}");

        $user->verificationRegistrationCodes()->create([
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(2),
        ]);

        return RegistrationResource::make($user);
    }

    public function verifyRegistrationCode(VerifyRegistrationCodeRequest $request): VerifyRegistrationCodeResource
    {
        $user = User::where('email', $request->email)->firstOrFail();

        if ($user->email_verified_at) {
            abort(400, 'Email уже подтвержден');
        }

        $verification = $user->verificationRegistrationCodes()
            ->latest()
            ->first();

        if (!$verification) {
            abort(404, 'Код не найден');
        }

        if ($verification->expires_at->isPast()) {
            abort(400, 'Код истёк');
        }

        if (!Hash::check($request->code, $verification->code_hash)) {
            abort(400, 'Неверный код');
        }

        $user->update([
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $user->verificationRegistrationCodes()->delete();

        return new VerifyRegistrationCodeResource(null);
    }

    public function login(LoginRequest $request): UserResource
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::guard('web')->attempt($credentials)) {
            abort(401, 'Неверный email или пароль.');
        }

        $request->session()->regenerate();

        return UserResource::make($request->user());
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return new ApiResource([
            'message' => 'Выход выполнен',
        ]);
    }
}
