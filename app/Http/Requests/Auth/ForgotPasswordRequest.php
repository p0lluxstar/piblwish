<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ForgotPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    // Правило exists не используется: ответ не должен раскрывать,
    // зарегистрирован ли email
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Email обязателен',
            'email.email' => 'Введите корректный email',
            'email.max' => 'Email не должен быть длиннее :max символов',
        ];
    }
}
