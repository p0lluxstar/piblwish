<?php

namespace App\Http\Requests\Wishlist;

use Illuminate\Foundation\Http\FormRequest;

// Снятие выбора гостя владельцем: checkedAt — время, на которое окно получило выбор
class ClearItemSelectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'checkedAt' => ['required', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'checkedAt.required' => 'Не указано время, на которое получен выбор гостей',
            'checkedAt.date' => 'Некорректное время, на которое получен выбор гостей',
        ];
    }
}
