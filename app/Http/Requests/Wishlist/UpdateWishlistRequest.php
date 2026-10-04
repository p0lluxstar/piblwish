<?php

namespace App\Http\Requests\Wishlist;

use App\Enums\WishlistColor;
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
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'color' => ['sometimes', Rule::enum(WishlistColor::class)],
            'items' => ['sometimes', 'array'],
            'items.*.label' => ['required_with:items', 'string', 'max:1000'],
            'items.*.isSelected' => ['sometimes', 'boolean'],
            // Только http(s): см. CreateWishlistRequest
            'items.*.url' => ['nullable', 'string', 'max:2048', 'url:http,https'],
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
        ];
    }
}
