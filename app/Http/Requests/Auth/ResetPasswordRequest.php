<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'code' => ['required', 'digits:6'],
            'password' => [
                'required',
                'string',
                'confirmed',
                Password::min(8),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Email обязателен',
            'email.email' => 'Введите корректный email',
            'email.max' => 'Email не должен быть длиннее :max символов',

            'code.required' => 'Код обязателен',
            'code.digits' => 'Код должен состоять из 6 цифр',

            'password.required' => 'Введите новый пароль',
            'password.confirmed' => 'Пароли не совпадают',
            'password.min' => 'Пароль должен быть минимум :min символов',
        ];
    }
}
