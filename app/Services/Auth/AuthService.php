<?php

namespace App\Services\Auth;

use App\Mail\PasswordChangedMail;
use App\Mail\PasswordResetCodeMail;
use App\Mail\VerificationCodeMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AuthService
{
    // Срок действия кода восстановления пароля
    private const RESET_CODE_TTL_MINUTES = 2;

    // Регистрация. Если email принадлежит аккаунту, который так и не
    // подтвердили, он не создаётся заново, а получает новые логин и пароль:
    // так регистрацию можно пройти повторно, а занять чужой адрес,
    // не подтвердив его, нельзя
    public function register(array $data): User
    {
        $attributes = [
            'username' => $data['username'],
            'password' => $data['password'],
        ];

        $user = User::pendingVerification()
            ->where('email', $data['email'])
            ->first();

        if ($user) {
            $user->update($attributes);
        } else {
            $user = User::create([
                ...$attributes,
                'email' => $data['email'],
            ]);
        }

        $this->sendVerificationCode($user);

        return $user;
    }

    // Повторная отправка кода подтверждения регистрации.
    // Для несуществующего, уже подтверждённого или удалённого аккаунта письмо
    // не отправляется, но ответ контроллера не отличается: по нему нельзя
    // определить, зарегистрирован ли email.
    public function resendVerificationCode(string $email): void
    {
        $user = User::pendingVerification()
            ->where('email', $email)
            ->first();

        if (! $user) {
            return;
        }

        $this->sendVerificationCode($user);
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

    // При $remember Laravel ставит долгоживущую cookie remember_web_*:
    // по ней вход восстанавливается после истечения сессии (SESSION_LIFETIME)
    public function login(
        array $credentials,
        bool $remember,
        Request $request
    ): User {
        // Деактивированные (удалённые) аккаунты не могут войти
        $credentials[] = fn ($query) => $query->whereNull('deactivated_at');

        $guard = Auth::guard('web');

        if (! $guard->validate($credentials)) {
            abort(401, 'Неверный email или пароль.');
        }

        // Пароль верный, но email не подтверждён — входить нельзя.
        // Новый код отправляется сразу: пароль уже проверен, поэтому это
        // владелец аккаунта, и он может завершить регистрацию со страницы
        // входа. Фронтенд по статусу 403 показывает форму ввода кода
        $user = $guard->getLastAttempted();

        if (! $user->is_active) {
            $this->sendVerificationCode($user);

            abort(403, 'Аккаунт не подтверждён. Мы отправили новый код на ваш email.');
        }

        $guard->login($user, $remember);

        $request->session()->regenerate();

        return $request->user();
    }

    // Выход только на текущем устройстве: cookie «Запомнить меня» этого
    // устройства удаляется, а remember_token не меняется, поэтому вход
    // на других устройствах сохраняется
    public function logout(Request $request): void
    {
        Auth::guard('web')->logoutCurrentDevice();

        $request->session()->invalidate();

        $request->session()->regenerateToken();
    }

    // Выход на всех устройствах, включая текущее: удаляются все сессии
    // и Sanctum-токены пользователя, а новый remember_token делает
    // недействительными cookie «Запомнить меня»
    public function logoutAllDevices(Request $request): void
    {
        $user = $request->user();

        DB::transaction(function () use ($user): void {
            DB::table('sessions')
                ->where('user_id', $user->getKey())
                ->delete();

            $user->setRememberToken(Str::random(60));
            $user->save();

            $user->tokens()->delete();
        });

        // Удаляет cookie «Запомнить меня» текущего устройства
        Auth::guard('web')->logoutCurrentDevice();

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

            // Новый remember_token делает недействительными cookie «Запомнить меня»:
            // иначе по ним вход восстановился бы без удалённых сессий
            $user->setRememberToken(Str::random(60));
            $user->save();

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

    // Создаёт новый код подтверждения регистрации и отправляет его письмом
    private function sendVerificationCode(User $user): void
    {
        $code = random_int(100000, 999999);

        Log::info("Код регистрации для {$user->email}: {$code}");

        // Действует только последний код, поэтому прежние удаляются
        $user->verificationRegistrationCodes()->delete();

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
