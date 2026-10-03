<?php

namespace App\Services\User;

use App\Mail\PasswordChangedMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class UserService
{
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

            $user->tokens()->delete();
        });

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

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();
    }
}
