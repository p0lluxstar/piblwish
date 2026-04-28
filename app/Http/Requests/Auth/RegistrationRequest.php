<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'username' => [
                'required',
                'string',
                'min:6',
                'max:50',
                'alpha_dash',
                'unique:users,username',
            ],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                'unique:users,email',
            ],
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
            'username.required' => 'Логин обязателен',
            'username.min' => 'Логин должен быть минимум :min символов',
            'username.max' => 'Логин не должен быть длиннее :max символов',
            'username.alpha_dash' => 'Логин может содержать только буквы, цифры, дефисы и подчеркивания',
            'username.unique' => 'Такой логин уже занят',

            'email.required' => 'Email обязателен',
            'email.email' => 'Введите корректный email',
            'email.max' => 'Email не должен быть длиннее :max символов',
            'email.unique' => 'Такой email уже зарегистрирован',

            'password.required' => 'Пароль обязателен',
            'password.confirmed' => 'Пароли не совпадают',
            'password.min' => 'Пароль должен быть минимум :min символов',
        ];
    }
}
