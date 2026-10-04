<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Mail\PasswordChangedMail;
use App\Mail\PasswordResetCodeMail;
use App\Mail\VerificationCodeMail;
use Illuminate\Support\Facades\Mail;

class AuthService
{
    // Срок действия кода восстановления пароля
    private const RESET_CODE_TTL_MINUTES = 10;

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

        try {
            Mail::to($user->email)
                ->queue(new VerificationCodeMail((string) $code));

            Log::info('Verification email queued', [
                'email' => $user->email,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to queue verification email', [
                'email' => $user->email,
                'exception' => $e,
            ]);
        }

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
        // Деактивированные (удалённые) аккаунты не могут войти
        $credentials[] = fn ($query) => $query->whereNull('deactivated_at');

        $guard = Auth::guard('web');

        if (! $guard->validate($credentials)) {
            abort(401, 'Неверный email или пароль.');
        }

        // Пароль верный, но email не подтверждён — входить нельзя
        $user = $guard->getLastAttempted();

        if (! $user->is_active) {
            abort(403, 'Аккаунт не подтверждён. Подтвердите email.');
        }

        $guard->login($user);

        $request->session()->regenerate();

        return $request->user();
    }

    public function logout(Request $request): void
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();
    }

    // Отправка кода восстановления пароля.
    // Для несуществующего, неподтверждённого или удалённого аккаунта письмо
    // не отправляется, но ответ контроллера не отличается: по нему нельзя
    // определить, зарегистрирован ли email.
    public function sendPasswordResetCode(string $email): void
    {
        $user = $this->findUserForPasswordReset($email);

        if (! $user) {
            return;
        }

        $code = random_int(100000, 999999);

        if (app()->isLocal()) {
            Log::info("Код восстановления пароля для {$user->email}: {$code}");
        }

        // Первичный ключ таблицы — email, поэтому новый код заменяет предыдущий
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            [
                'token' => Hash::make((string) $code),
                'created_at' => now(),
            ],
        );

        try {
            Mail::to($user->email)->queue(new PasswordResetCodeMail(
                username: $user->username,
                code: (string) $code,
                expiresInMinutes: self::RESET_CODE_TTL_MINUTES,
            ));
        } catch (\Throwable $e) {
            Log::error('Failed to queue password reset email', [
                'email' => $user->email,
                'exception' => $e,
            ]);
        }
    }

    // Установка нового пароля по коду из письма.
    // Все сессии и токены пользователя завершаются: если пароль восстанавливают
    // после взлома, доступ злоумышленника прекращается.
    public function resetPassword(array $data, Request $request): void
    {
        $user = $this->findUserForPasswordReset($data['email']);

        $reset = $user
            ? DB::table('password_reset_tokens')->where('email', $user->email)->first()
            : null;

        // Причина отказа не уточняется, чтобы не раскрывать наличие аккаунта
        $isValid = $reset
            && now()->subMinutes(self::RESET_CODE_TTL_MINUTES)->lt($reset->created_at)
            && Hash::check($data['code'], $reset->token);

        if (! $isValid) {
            abort(400, 'Неверный или просроченный код');
        }

        DB::transaction(function () use ($user, $data): void {
            // Хеширование выполняет каст 'password' => 'hashed' в модели User
            $user->update([
                'password' => $data['password'],
            ]);

            DB::table('password_reset_tokens')
                ->where('email', $user->email)
                ->delete();

            DB::table('sessions')
                ->where('user_id', $user->getKey())
                ->delete();

            $user->tokens()->delete();
        });

        // Ошибка постановки письма в очередь не отменяет уже выполненную смену пароля
        try {
            Mail::to($user->email)->queue(new PasswordChangedMail(
                username: $user->username,
                changedAt: now()->timezone('Europe/Moscow')->format('d.m.Y H:i').' (МСК)',
                ipAddress: $request->ip(),
            ));
        } catch (\Throwable $e) {
            Log::error('Failed to queue password changed email', [
                'email' => $user->email,
                'exception' => $e,
            ]);
        }
    }

    // Восстановить пароль можно только у подтверждённого и не удалённого аккаунта
    private function findUserForPasswordReset(string $email): ?User
    {
        return User::where('email', $email)
            ->where('is_active', true)
            ->whereNull('deactivated_at')
            ->first();
    }
}
