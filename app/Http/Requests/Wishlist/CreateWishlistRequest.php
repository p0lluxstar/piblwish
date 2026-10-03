<?php

namespace App\Http\Requests\Wishlist;

use App\Enums\WishlistColor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateWishlistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],

            // Необязателен: без него список получает white
            'color' => ['sometimes', Rule::enum(WishlistColor::class)],

            'items' => ['required', 'array', 'min:1'],

            'items.*.label' => [
                'required',
                'string',
                'max:1000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Название обязательно',

            'color.enum' => 'Недопустимый цвет списка',

            'items.required' => 'Добавьте хотя бы один элемент',

            'items.*.label.required' => 'Описание элемента обязательно',
        ];
    }
}
