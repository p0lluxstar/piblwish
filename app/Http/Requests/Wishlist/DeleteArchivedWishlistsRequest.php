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
            // Больше, чем списков у пользователя, в архиве быть не может
            'ids' => ['required', 'array', 'min:1', 'max:'.config('wishlist.max_wishlists')],
            'ids.*' => ['required', 'string', 'distinct'],
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required' => 'Не указаны списки для удаления',
            'ids.array' => 'Некорректный перечень списков для удаления',
            'ids.min' => 'Не указаны списки для удаления',
            'ids.max' => 'Слишком много списков для удаления за раз',
            'ids.*.required' => 'Некорректный идентификатор списка',
            'ids.*.string' => 'Некорректный идентификатор списка',
            'ids.*.distinct' => 'Идентификаторы списков повторяются',
        ];
    }
}
