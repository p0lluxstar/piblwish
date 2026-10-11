<?php

namespace App\Http\Requests\Wishlist;

use Illuminate\Foundation\Http\FormRequest;

// Удаление архива: ids — списки, которые владелец видел в архиве, когда подтверждал удаление
class DeleteArchivedWishlistsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'string', 'distinct'],
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required' => 'Не указаны списки для удаления',
            'ids.array' => 'Некорректный перечень списков для удаления',
            'ids.min' => 'Не указаны списки для удаления',
            'ids.*.required' => 'Некорректный идентификатор списка',
            'ids.*.string' => 'Некорректный идентификатор списка',
            'ids.*.distinct' => 'Идентификаторы списков повторяются',
        ];
    }
}
