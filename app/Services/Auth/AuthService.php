<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthService
{
    public function register(array $data): User
    {
        $user = User::create([
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        $code = random_int(100000, 999999);

        Log::info("Код регистрации для {$user->email}: {$code}");

        $user->verificationRegistrationCodes()->create([
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(2),
        ]);

        return $user;
    }

    public function verifyRegistrationCode(array $data): void
    {
        $user = User::where('email', $data['email'])
            ->firstOrFail();

        if ($user->email_verified_at) {
            abort(400, 'Email уже подтвержден');
        }

        $verification = $user->verificationRegistrationCodes()
            ->latest()
            ->first();

        if (! $verification) {
            abort(404, 'Код не найден');
        }

        if ($verification->expires_at->isPast()) {
            abort(400, 'Код истёк');
        }

        if (! Hash::check($data['code'], $verification->code_hash)) {
            abort(400, 'Неверный код');
        }

        $user->update([
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $user->verificationRegistrationCodes()->delete();
    }

    public function login(
        array $credentials,
        Request $request
    ): User {
        if (! Auth::guard('web')->attempt($credentials)) {
            abort(401, 'Неверный email или пароль.');
        }

        $request->session()->regenerate();

        return $request->user();
    }

    public function logout(Request $request): void
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();
    }
}
