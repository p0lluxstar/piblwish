<?php

namespace App\Http\Requests\User;

use App\Enums\AppBackground;
use App\Services\Reminder\DueDateReminderService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Запрос меняет одну или несколько настроек; пустой запрос отклоняется
            'background' => ['required_without_all:showFriendsEvents,emailReminders,reminderDays', Rule::enum(AppBackground::class)],
            'showFriendsEvents' => ['sometimes', 'boolean'],
            'emailReminders' => ['sometimes', 'boolean'],
            // За сколько дней до даты списка приходит напоминание
            'reminderDays' => ['sometimes', 'integer', 'between:'.DueDateReminderService::MIN_DAYS.','.DueDateReminderService::MAX_DAYS],
        ];
    }

    public function messages(): array
    {
        return [
            'background.required_without_all' => 'Выберите фон',
            'background.enum' => 'Недопустимый фон',
            'showFriendsEvents.boolean' => 'Недопустимое значение настройки',
            'emailReminders.boolean' => 'Недопустимое значение настройки',
            'reminderDays.integer' => 'Срок напоминания — от 1 до 7 дней',
            'reminderDays.between' => 'Срок напоминания — от 1 до 7 дней',
        ];
    }
}
