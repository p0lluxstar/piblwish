<?php

namespace App\Services\User;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class UserService
{
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
