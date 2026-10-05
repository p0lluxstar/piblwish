<?php

namespace App\Http\Requests\Wishlist;

use Illuminate\Foundation\Http\FormRequest;

// Отметка дела в списке дел прямо с карточки, без режима редактирования
class UpdateWishlistItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'isSelected' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'isSelected.required' => 'Не указано, выполнено ли дело',
        ];
    }
}
