<?php

namespace App\Services\User;

use App\Enums\AppBackground;
use App\Mail\EmailChangeCodeMail;
use App\Mail\EmailChangedMail;
use App\Mail\PasswordChangedMail;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class UserService
{
    // Срок действия кода подтверждения нового email
    private const EMAIL_CHANGE_CODE_TTL_MINUTES = 15;

    // Число неверных попыток ввода кода, после которого запрос удаляется
    private const EMAIL_CHANGE_MAX_ATTEMPTS = 5;

    // Изменение настроек текущего пользователя; пока это только фон приложения
    public function updateSettings(User $user, AppBackground $background): User
    {
        $user->update([
            'background' => $background,
        ]);

        return $user;
    }

    // Смена пароля текущего пользователя.
    // Текущий пароль уже проверен в ChangePasswordRequest (правило current_password).
    // Пользователь остаётся в системе, сессии на других устройствах завершаются.
    public function changePassword(Request $request, string $newPassword): void
    {
        $user = $request->user();
        $currentSessionId = $request->session()->getId();

        DB::transaction(function () use ($user, $newPassword, $currentSessionId): void {
            // Хеширование выполняет каст 'password' => 'hashed' в модели User
            $user->update([
                'password' => $newPassword,
            ]);

            DB::table('sessions')
                ->where('user_id', $user->getKey())
                ->where('id', '!=', $currentSessionId)
                ->delete();

            // Новый remember_token делает недействительными cookie «Запомнить меня»
            // на других устройствах: иначе по ним вход восстановился бы без сессии
            $user->setRememberToken(Str::random(60));
            $user->save();

            $user->tokens()->delete();
        });

        // Если на этом устройстве вход был запомнен, выдаём cookie с новым токеном
        $guard = Auth::guard('web');

        if ($request->cookies->has($guard->getRecallerName())) {
            $guard->login($user, true);
        }

        // Новый идентификатор текущей сессии; старая запись удаляется,
        // чтобы перехваченный ранее cookie сессии перестал действовать
        $request->session()->regenerate(true);

        // Уведомляем владельца: если пароль сменил не он, он узнает об этом.
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

    // Запрос на смену email: код отправляется на новый адрес.
    // Текущий пароль и свободность адреса уже проверены в RequestEmailChangeRequest.
    // Новый запрос заменяет предыдущий вместе с его кодом и счётчиком попыток.
    public function requestEmailChange(User $user, string $newEmail): void
    {
        $code = (string) random_int(100000, 999999);

        if (app()->isLocal()) {
            Log::info("Код смены email для {$newEmail}: {$code}");
        }

        $user->emailChangeRequest()->updateOrCreate([], [
            'new_email' => $newEmail,
            'code_hash' => Hash::make($code),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(self::EMAIL_CHANGE_CODE_TTL_MINUTES),
        ]);

        // Без письма пользователь не сможет подтвердить адрес, поэтому
        // ошибка постановки в очередь возвращается клиенту, а запрос удаляется
        try {
            Mail::to($newEmail)->queue(new EmailChangeCodeMail(
                username: $user->username,
                code: $code,
                expiresInMinutes: self::EMAIL_CHANGE_CODE_TTL_MINUTES,
            ));
        } catch (\Throwable $e) {
            Log::error('Failed to queue email change code', [
                'user_id' => $user->getKey(),
                'exception' => $e,
            ]);

            $user->emailChangeRequest()->delete();

            abort(503, 'Не удалось отправить письмо. Попробуйте позже');
        }
    }

    // Подтверждение нового email кодом из письма.
    // После EMAIL_CHANGE_MAX_ATTEMPTS неверных попыток запрос удаляется.
    // Сессии не завершаются: пароль не меняется, а владение адресом подтверждено.
    public function confirmEmailChange(Request $request, string $code): User
    {
        $user = $request->user();
        $changeRequest = $user->emailChangeRequest()->first();

        if (! $changeRequest) {
            abort(404, 'Запрос на смену email не найден. Запросите код заново');
        }

        if ($changeRequest->isExpired()) {
            $changeRequest->delete();

            abort(400, 'Код истёк. Запросите новый');
        }

        if (! Hash::check($code, $changeRequest->code_hash)) {
            $changeRequest->increment('attempts');

            if ($changeRequest->attempts >= self::EMAIL_CHANGE_MAX_ATTEMPTS) {
                $changeRequest->delete();

                abort(400, 'Слишком много неверных попыток. Запросите новый код');
            }

            abort(400, 'Неверный код');
        }

        $oldEmail = $user->email;
        $newEmail = $changeRequest->new_email;

        // Пока пользователь ждал письмо, адрес могли занять
        $rejectTakenEmail = function () use ($changeRequest): never {
            $changeRequest->delete();

            abort(409, 'Этот email уже занят. Укажите другой адрес');
        };

        $isTaken = User::where('email', $newEmail)
            ->where('id', '!=', $user->getKey())
            ->exists();

        if ($isTaken) {
            $rejectTakenEmail();
        }

        // Уникальный индекс users.email закрывает гонку между проверкой и записью
        try {
            DB::transaction(function () use ($user, $changeRequest, $oldEmail, $newEmail): void {
                $user->update([
                    'email' => $newEmail,
                    'email_verified_at' => now(),
                ]);

                $changeRequest->delete();

                // Код восстановления пароля привязан к прежнему адресу
                DB::table('password_reset_tokens')
                    ->where('email', $oldEmail)
                    ->delete();
            });
        } catch (UniqueConstraintViolationException) {
            $rejectTakenEmail();
        }

        // Уведомляем прежний адрес: если email сменил не владелец, он узнает об этом.
        // Ошибка постановки письма в очередь не отменяет уже выполненную смену
        try {
            Mail::to($oldEmail)->queue(new EmailChangedMail(
                username: $user->username,
                newEmail: $newEmail,
                changedAt: now()->timezone('Europe/Moscow')->format('d.m.Y H:i').' (МСК)',
                ipAddress: $request->ip(),
            ));
        } catch (\Throwable $e) {
            Log::error('Failed to queue email changed notification', [
                'user_id' => $user->getKey(),
                'exception' => $e,
            ]);
        }

        return $user;
    }

    // Удаление аккаунта текущего пользователя.
    // Запись пользователя не удаляется, а деактивируется через deactivated_at.
    // Вишлисты удаляются, их позиции — каскадно на уровне БД.
    public function deleteAccount(Request $request): void
    {
        $user = $request->user();

        DB::transaction(function () use ($user): void {
            $user->wishlists()->delete();

            $user->update([
                'deactivated_at' => now(),
            ]);

            // Завершаем сессии пользователя на других устройствах:
            // запись пользователя остаётся, поэтому они продолжили бы работать
            DB::table('sessions')
                ->where('user_id', $user->getKey())
                ->delete();

            $user->tokens()->delete();
        });

        // logout() также меняет remember_token, поэтому cookie «Запомнить меня»
        // перестают действовать на всех устройствах
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();
    }
}
