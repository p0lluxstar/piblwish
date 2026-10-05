<?php

namespace App\Http\Requests\Wishlist;

use App\Enums\WishlistColor;
use App\Enums\WishlistItemPriority;
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

            // Режим сюрприза; по умолчанию выключен
            'hideSelections' => ['sometimes', 'boolean'],

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

            // Приоритет позиции (1–3) необязателен
            'items.*.priority' => [
                'nullable',
                Rule::enum(WishlistItemPriority::class),
            ],

            // Стоимость в целых рублях необязательна; верхняя граница отсекает
            // случайно введённые лишние цифры
            'items.*.price' => [
                'nullable',
                'integer',
                'min:0',
                'max:10000000',
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

            'items.*.priority.enum' => 'Недопустимый приоритет позиции',

            'items.*.price.integer' => 'Стоимость должна быть целым числом рублей',
            'items.*.price.min' => 'Стоимость не может быть отрицательной',
            'items.*.price.max' => 'Стоимость не может превышать 10 000 000 ₽',
        ];
    }
}
