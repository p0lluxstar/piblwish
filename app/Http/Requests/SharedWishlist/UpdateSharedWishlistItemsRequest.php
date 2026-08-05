<?php

namespace App\Http\Requests\SharedWishlist;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSharedWishlistItemsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'item_ids' => ['required', 'array', 'min:1'],
            'item_ids.*' => ['required', 'ulid'],
        ];
    }
}
