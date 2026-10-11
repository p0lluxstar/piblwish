<?php

namespace App\Http\Resources\Auth;

use App\Enums\AppBackground;
use App\Http\Resources\ApiResource;
use App\Services\Reminder\DueDateReminderService;

class UserResource extends ApiResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'email' => $this->email,
            // Ключ фона приложения из App\Enums\AppBackground. У модели, созданной
            // в этом запросе и не перечитанной из БД, атрибута ещё нет
            'background' => ($this->background ?? AppBackground::Blossom)->value,
            // Показ строки «Скоро у друзей» в дашборде
            'showFriendsEvents' => $this->show_friends_events ?? true,
            // Письма-напоминания перед датой своих и сохранённых чужих списков
            'emailReminders' => $this->email_reminders ?? true,
            // За сколько дней до даты списка приходит напоминание (1–7)
            'reminderDays' => $this->reminder_days ?? DueDateReminderService::DEFAULT_DAYS,
            // Адрес фотографии или null — тогда показывается первая буква имени
            'avatarUrl' => $this->avatarUrl(),
            // Дата регистрации, показывается в окне настроек аккаунта
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
