<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'current_password' => [
                'required',
                'string',
                'current_password:web',
            ],
            'password' => [
                'required',
                'string',
                'confirmed',
                'different:current_password',
                Password::min(8),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.required' => 'Введите текущий пароль',
            'current_password.current_password' => 'Неверный текущий пароль',

            'password.required' => 'Введите новый пароль',
            'password.confirmed' => 'Пароли не совпадают',
            'password.different' => 'Новый пароль должен отличаться от текущего',
            'password.min' => 'Пароль должен быть минимум :min символов',
        ];
    }
}
