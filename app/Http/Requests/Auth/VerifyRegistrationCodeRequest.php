<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class VerifyRegistrationCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'exists:users,email'],
            'code' => ['required', 'digits:6'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Email обязателен для подтверждения',
            'email.email' => 'Некорректный формат email',
            'email.exists' => 'Пользователь с таким email не найден',
            'code.required' => 'Код обязателен',
            'code.digits' => 'Код должен состоять из 6 цифр',
        ];
    }
}
