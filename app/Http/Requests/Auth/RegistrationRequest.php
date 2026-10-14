<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Rules\NoControlCharacters;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    // Неподтверждённый аккаунт с этим email регистрация перезаписывает
    // (AuthService::register), поэтому его email и логин не считаются занятыми
    public function rules(): array
    {
        $pendingUserId = User::pendingVerification()
            ->where('email', (string) $this->input('email'))
            ->value('id');

        return [
            'username' => [
                'required',
                'string',
                'min:3',
                'max:30',
                'alpha_dash',
                Rule::unique('users', 'username')->ignore($pendingUserId),
            ],
            'email' => [
                'required',
                'string',
                'email',
                new NoControlCharacters,
                'max:255',
                Rule::unique('users', 'email')->ignore($pendingUserId),
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
            'username.min' => 'Логин должен быть не короче :min символов',
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
