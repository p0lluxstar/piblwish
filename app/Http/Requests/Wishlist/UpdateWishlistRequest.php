<?php

namespace App\Http\Requests\Wishlist;

use App\Enums\WishlistColor;
use App\Enums\WishlistItemPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWishlistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Тип списка после создания не меняется, поэтому поля type здесь нет.
        // Ссылку, цену, приоритет и режим сюрприза у списка дел очищает WishlistService
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'color' => ['sometimes', Rule::enum(WishlistColor::class)],
            'hideSelections' => ['sometimes', 'boolean'],
            'items' => ['sometimes', 'array'],
            // id существующей позиции: по нему в режиме сюрприза сохраняется выбор гостей
            'items.*.id' => ['sometimes', 'nullable', 'string'],
            'items.*.label' => ['required_with:items', 'string', 'max:1000'],
            'items.*.isSelected' => ['sometimes', 'boolean'],
            // Только http(s): см. CreateWishlistRequest
            'items.*.url' => ['nullable', 'string', 'max:2048', 'url:http,https'],
            'items.*.priority' => ['nullable', Rule::enum(WishlistItemPriority::class)],
            // Стоимость в целых рублях: см. CreateWishlistRequest
            'items.*.price' => ['nullable', 'integer', 'min:0', 'max:10000000'],
        ];
    }

    public function messages(): array
    {
        return [
            'color.enum' => 'Недопустимый цвет списка',
            'items.required' => 'Добавьте хотя бы один элемент',
            'items.*.label.required_with' => 'Описание элемента обязательно',
            'items.*.url.url' => 'Некорректная ссылка на товар',
            'items.*.url.max' => 'Ссылка на товар слишком длинная',
            'items.*.priority.enum' => 'Недопустимый приоритет позиции',
            'items.*.price.integer' => 'Стоимость должна быть целым числом рублей',
            'items.*.price.min' => 'Стоимость не может быть отрицательной',
            'items.*.price.max' => 'Стоимость не может превышать 10 000 000 ₽',
        ];
    }
}
