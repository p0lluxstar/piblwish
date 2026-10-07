<?php

namespace App\Http\Requests\User;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RequestEmailChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => [
                'bail',
                'required',
                'string',
                'email',
                'max:255',
                // Сравнение без учёта регистра, как в колонке users.email
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (mb_strtolower($value) === mb_strtolower($this->user()->email)) {
                        $fail('Это ваш текущий email');
                    }
                },
                Rule::unique('users', 'email')->ignore($this->user()->getKey()),
            ],
            'current_password' => [
                'required',
                'string',
                'current_password:web',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Введите новый email',
            'email.email' => 'Введите корректный email',
            'email.max' => 'Email не должен быть длиннее :max символов',
            'email.unique' => 'Такой email уже зарегистрирован',

            'current_password.required' => 'Введите текущий пароль',
            'current_password.current_password' => 'Неверный текущий пароль',
        ];
    }
}
