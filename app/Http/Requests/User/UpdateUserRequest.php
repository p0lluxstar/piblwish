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
            'background' => ['required', Rule::enum(AppBackground::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'background.required' => 'Выберите фон',
            'background.enum' => 'Недопустимый фон',
        ];
    }
}
