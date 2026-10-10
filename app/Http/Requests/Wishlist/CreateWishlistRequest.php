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

            // Доступ по ссылке: у списка желаний по умолчанию включён, у списка дел
            // (только просмотр) — выключен. Заметка по ссылке недоступна никогда
            'isShared' => ['sometimes', 'boolean', 'prohibited_if:type,note'],

            // Разрешение гостям отмечать дела по ссылке; по умолчанию выключено.
            // Действует, только если включён isShared
            'guestsCanCheck' => ['sometimes', 'boolean', 'prohibited_unless:type,todo'],

            // Должен ли гость указать имя, отмечая дела; по умолчанию true.
            // Действует, только если включён guestsCanCheck
            'guestNameRequired' => ['sometimes', 'boolean', 'prohibited_unless:type,todo'],

            // Срок списка дел или дата события списка желаний; необязательна.
            // У заметки даты нет. Границы отсекают опечатки в годе
            'dueDate' => [
                'sometimes',
                'nullable',
                'prohibited_if:type,note',
                'date_format:Y-m-d',
                'after_or_equal:2000-01-01',
                'before:2100-01-01',
            ],

            // У заметки нет позиций
            'items' => ['prohibited_if:type,note', 'required_unless:type,note', 'array', 'min:1'],

            'items.*.label' => [
                'required',
                'string',
                'max:1000',
            ],

            // Ссылки на товар необязательны, не больше трёх; только http(s), чтобы
            // на общей странице нельзя было подставить javascript: и другие опасные схемы.
            // У позиций списка дел нет ссылок, приоритета и стоимости
            'items.*.urls' => [
                'prohibited_if:type,todo',
                'nullable',
                'array',
                'max:3',
            ],

            // Пустые элементы WishlistService отбрасывает
            'items.*.urls.*' => [
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

            'isShared.prohibited_if' => 'Заметка по ссылке недоступна',
            'guestsCanCheck.prohibited_unless' => 'Отмечать позиции по ссылке гости могут только в списке дел',
            'guestNameRequired.prohibited_unless' => 'Имя гостя запрашивается только в списке дел',

            'dueDate.prohibited_if' => 'У заметки нет даты',
            'dueDate.date_format' => 'Некорректная дата',
            'dueDate.after_or_equal' => 'Некорректная дата',
            'dueDate.before' => 'Некорректная дата',

            'items.required_unless' => 'Добавьте хотя бы один элемент',
            'items.prohibited_if' => 'У заметки нет позиций',

            'items.*.label.required' => 'Описание элемента обязательно',

            'items.*.urls.prohibited_if' => 'У дела не может быть ссылки',
            'items.*.urls.array' => 'Некорректный список ссылок на товар',
            'items.*.urls.max' => 'Можно указать не больше трёх ссылок на товар',
            'items.*.urls.*.string' => 'Некорректная ссылка на товар',
            'items.*.urls.*.url' => 'Некорректная ссылка на товар',
            'items.*.urls.*.max' => 'Ссылка на товар слишком длинная',

            'items.*.priority.prohibited_if' => 'У дела не может быть приоритета',
            'items.*.priority.enum' => 'Недопустимый приоритет позиции',

            'items.*.price.prohibited_if' => 'У дела не может быть стоимости',
            'items.*.price.integer' => 'Стоимость должна быть целым числом рублей',
            'items.*.price.min' => 'Стоимость не может быть отрицательной',
            'items.*.price.max' => 'Стоимость не может превышать 10 000 000 ₽',
        ];
    }
}
