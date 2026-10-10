<?php

namespace App\Services\User;

use App\Enums\AppBackground;
use App\Mail\EmailChangeCodeMail;
use App\Mail\EmailChangedMail;
use App\Mail\PasswordChangedMail;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;

class UserService
{
    // Срок действия кода подтверждения нового email
    private const EMAIL_CHANGE_CODE_TTL_MINUTES = 15;

    // Число неверных попыток ввода кода, после которого запрос удаляется
    private const EMAIL_CHANGE_MAX_ATTEMPTS = 5;

    // Сторона квадратной фотографии пользователя в пикселях. Самый крупный аватар
    // в интерфейсе — 52 px, остальное — запас для экранов высокой плотности
    // и более крупных аватаров: оригинал не хранится, увеличить фото потом нельзя
    private const AVATAR_SIZE = 256;

    // Качество WebP (0–100)
    private const AVATAR_QUALITY = 85;

    // Изменение настроек текущего пользователя: фон приложения и показ строки
    // «Скоро у друзей» в дашборде; null означает, что настройка не меняется
    public function updateSettings(
        User $user,
        ?AppBackground $background,
        ?bool $showFriendsEvents = null
    ): User {
        $user->update(array_filter([
            'background' => $background,
            'show_friends_events' => $showFriendsEvents,
        ], fn ($value) => $value !== null));

        return $user;
    }

    // Загрузка фотографии пользователя вместо буквы в кружке.
    // Исходный файл не сохраняется: изображение всегда перекодируется в WebP.
    // Это удаляет метаданные (EXIF с координатами съёмки) и любое содержимое,
    // кроме пикселей. Ориентация по EXIF учитывается до перекодирования.
    // Новый файл получает новое имя, поэтому браузер не покажет прежнее фото из кэша;
    // прежний файл удаляется только после того, как новый путь записан в БД.
    public function updateAvatar(User $user, UploadedFile $file): User
    {
        try {
            // decodeAnimation: false — у анимированного GIF или WebP берётся первый кадр
            $encoded = ImageManager::gd(decodeAnimation: false, strip: true)
                ->read($file->getRealPath())
                ->cover(self::AVATAR_SIZE, self::AVATAR_SIZE)
                ->toWebp(quality: self::AVATAR_QUALITY);
        } catch (\Throwable $e) {
            Log::warning('Failed to process avatar image', [
                'user_id' => $user->getKey(),
                'exception' => $e,
            ]);

            abort(422, 'Не удалось обработать изображение. Выберите другой файл');
        }

        $disk = Storage::disk('public');
        // ULID строчными буквами, как id пользователя в имени каталога (HasUlids)
        $path = $this->avatarDirectory($user).'/'.Str::lower((string) Str::ulid()).'.webp';

        if (! $disk->put($path, (string) $encoded)) {
            Log::error('Failed to store avatar file', [
                'user_id' => $user->getKey(),
                'path' => $path,
            ]);

            abort(500, 'Не удалось сохранить фотографию. Попробуйте позже');
        }

        $oldPath = $user->avatar_path;

        try {
            $user->update([
                'avatar_path' => $path,
            ]);
        } catch (\Throwable $e) {
            // Путь не записан, поэтому на новый файл ничего не ссылается
            $disk->delete($path);

            throw $e;
        }

        if ($oldPath) {
            $disk->delete($oldPath);
        }

        return $user;
    }

    // Удаление фотографии пользователя: снова показывается первая буква имени
    public function deleteAvatar(User $user): User
    {
        $oldPath = $user->avatar_path;

        if (! $oldPath) {
            return $user;
        }

        $user->update([
            'avatar_path' => null,
        ]);

        Storage::disk('public')->delete($oldPath);

        return $user;
    }

    // Каталог с фотографиями пользователя. Обычно в нём один файл, но при сбое
    // между записью нового файла и удалением прежнего может остаться лишний,
    // поэтому при удалении аккаунта удаляется весь каталог
    private function avatarDirectory(User $user): string
    {
        return 'avatars/'.$user->getKey();
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
    // Добавленные к себе чужие списки удаляются явно: запись пользователя
    // остаётся, поэтому каскад по user_id не срабатывает.
    public function deleteAccount(Request $request): void
    {
        $user = $request->user();

        DB::transaction(function () use ($user): void {
            $user->wishlists()->delete();
            $user->savedWishlists()->delete();

            $user->update([
                'deactivated_at' => now(),
                'avatar_path' => null,
            ]);

            // Завершаем сессии пользователя на других устройствах:
            // запись пользователя остаётся, поэтому они продолжили бы работать
            DB::table('sessions')
                ->where('user_id', $user->getKey())
                ->delete();

            $user->tokens()->delete();
        });

        // Фотография больше нигде не показывается. Каталог удаляется после транзакции:
        // при её откате путь в БД остался бы, а файла уже не было бы
        Storage::disk('public')->deleteDirectory($this->avatarDirectory($user));

        // logout() также меняет remember_token, поэтому cookie «Запомнить меня»
        // перестают действовать на всех устройствах
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();
    }
}
