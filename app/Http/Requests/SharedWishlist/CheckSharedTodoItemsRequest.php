<?php

namespace App\Http\Requests\SharedWishlist;

use Illuminate\Foundation\Http\FormRequest;

class CheckSharedTodoItemsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Дела, которые гость отмечает выполненными
            'item_ids' => ['required', 'array', 'min:1'],
            'item_ids.*' => ['required', 'ulid'],

            // Имя гостя для подписи под делами. Обязательно ли оно, зависит
            // от настройки списка: это проверяет SharedWishlistService
            'name' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.max' => 'Имя не может быть длиннее 50 символов',
        ];
    }
}
