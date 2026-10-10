<?php

namespace App\Http\Requests\User;

use App\Enums\AppBackground;
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
            // Запрос меняет одну или обе настройки; пустой запрос отклоняется
            'background' => ['required_without:showFriendsEvents', Rule::enum(AppBackground::class)],
            'showFriendsEvents' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'background.required_without' => 'Выберите фон',
            'background.enum' => 'Недопустимый фон',
            'showFriendsEvents.boolean' => 'Недопустимое значение настройки',
        ];
    }
}
