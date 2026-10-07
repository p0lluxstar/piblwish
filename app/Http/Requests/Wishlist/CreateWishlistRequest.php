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

            // У заметки нет названия: всё её содержимое — текст в content
            'title' => ['prohibited_if:type,note', 'required_unless:type,note', 'string', 'max:255'],

            // Текст заметки; у списков желаний и дел его нет
            'content' => ['prohibited_unless:type,note', 'required_if:type,note', 'string', 'max:5000'],

            // Необязателен: без него список получает white
            'color' => ['sometimes', Rule::enum(WishlistColor::class)],

            // Режим сюрприза; по умолчанию выключен. У списка дел и заметки нет гостей,
            // поэтому включить режим сюрприза для них нельзя
            'hideSelections' => ['sometimes', 'boolean', 'declined_if:type,todo,note'],

            // Доступ по ссылке для просмотра; по умолчанию выключен. Включается только
            // у списка дел: список желаний доступен по ссылке всегда, заметка — никогда
            'isShared' => ['sometimes', 'boolean', 'prohibited_unless:type,todo'],

            // Разрешение гостям отмечать дела по ссылке; по умолчанию выключено.
            // Действует, только если включён isShared
            'guestsCanCheck' => ['sometimes', 'boolean', 'prohibited_unless:type,todo'],

            // Должен ли гость указать имя, отмечая дела; по умолчанию true.
            // Действует, только если включён guestsCanCheck
            'guestNameRequired' => ['sometimes', 'boolean', 'prohibited_unless:type,todo'],

            // У заметки нет позиций
            'items' => ['prohibited_if:type,note', 'required_unless:type,note', 'array', 'min:1'],

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

            'title.required_unless' => 'Название обязательно',
            'title.prohibited_if' => 'У заметки нет названия',

            'content.required_if' => 'Текст заметки обязателен',
            'content.prohibited_unless' => 'Текст есть только у заметки',
            'content.max' => 'Текст заметки не может быть длиннее 5000 символов',

            'color.enum' => 'Недопустимый цвет списка',

            'hideSelections.declined_if' => 'Режим сюрприза есть только у списка желаний',

            'isShared.prohibited_unless' => 'Доступ по ссылке включается только у списка дел',
            'guestsCanCheck.prohibited_unless' => 'Отмечать позиции по ссылке гости могут только в списке дел',
            'guestNameRequired.prohibited_unless' => 'Имя гостя запрашивается только в списке дел',

            'items.required_unless' => 'Добавьте хотя бы один элемент',
            'items.prohibited_if' => 'У заметки нет позиций',

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
