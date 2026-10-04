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

            // Ссылка на товар необязательна; только http(s), чтобы на общей странице
            // нельзя было подставить javascript: и другие опасные схемы
            'items.*.url' => [
                'nullable',
                'string',
                'max:2048',
                'url:http,https',
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

            'items.*.url.url' => 'Некорректная ссылка на товар',
            'items.*.url.max' => 'Ссылка на товар слишком длинная',
        ];
    }
}
