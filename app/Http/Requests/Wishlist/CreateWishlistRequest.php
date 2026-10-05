<?php

namespace App\Http\Requests\Wishlist;

use App\Enums\WishlistColor;
use App\Enums\WishlistItemPriority;
use App\Enums\WishlistType;
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
            // Необязателен: без него создаётся список желаний (gift).
            // Тип задаётся только при создании и потом не меняется
            'type' => ['sometimes', Rule::enum(WishlistType::class)],

            'title' => ['required', 'string', 'max:255'],

            // Необязателен: без него список получает white
            'color' => ['sometimes', Rule::enum(WishlistColor::class)],

            // Режим сюрприза; по умолчанию выключен. У списка дел нет гостей,
            // поэтому включить режим сюрприза для него нельзя
            'hideSelections' => ['sometimes', 'boolean', 'declined_if:type,todo'],

            'items' => ['required', 'array', 'min:1'],

            'items.*.label' => [
                'required',
                'string',
                'max:1000',
            ],

            // Ссылка на товар необязательна; только http(s), чтобы на общей странице
            // нельзя было подставить javascript: и другие опасные схемы.
            // У позиций списка дел нет ссылки, приоритета и стоимости
            'items.*.url' => [
                'prohibited_if:type,todo',
                'nullable',
                'string',
                'max:2048',
                'url:http,https',
            ],

            // Приоритет позиции (1–3) необязателен
            'items.*.priority' => [
                'prohibited_if:type,todo',
                'nullable',
                Rule::enum(WishlistItemPriority::class),
            ],

            // Стоимость в целых рублях необязательна; верхняя граница отсекает
            // случайно введённые лишние цифры
            'items.*.price' => [
                'prohibited_if:type,todo',
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
            'type.enum' => 'Недопустимый тип списка',

            'title.required' => 'Название обязательно',

            'color.enum' => 'Недопустимый цвет списка',

            'hideSelections.declined_if' => 'У списка дел нет режима сюрприза',

            'items.required' => 'Добавьте хотя бы один элемент',

            'items.*.label.required' => 'Описание элемента обязательно',

            'items.*.url.prohibited_if' => 'У дела не может быть ссылки',
            'items.*.url.url' => 'Некорректная ссылка на товар',
            'items.*.url.max' => 'Ссылка на товар слишком длинная',

            'items.*.priority.prohibited_if' => 'У дела не может быть приоритета',
            'items.*.priority.enum' => 'Недопустимый приоритет позиции',

            'items.*.price.prohibited_if' => 'У дела не может быть стоимости',
            'items.*.price.integer' => 'Стоимость должна быть целым числом рублей',
            'items.*.price.min' => 'Стоимость не может быть отрицательной',
            'items.*.price.max' => 'Стоимость не может превышать 10 000 000 ₽',
        ];
    }
}
