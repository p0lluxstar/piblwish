<?php

namespace App\Http\Requests\Wishlist;

use Illuminate\Foundation\Http\FormRequest;

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
            'items' => ['sometimes', 'array'],
            'items.*.label' => ['required_with:items', 'string', 'max:1000'],
            'items.*.isSelected' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Добавьте хотя бы один элемент',
            'items.*.label.required_with' => 'Описание элемента обязательно',
        ];
    }
}
