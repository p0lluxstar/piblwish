<?php

namespace App\Http\Requests\SharedWishlist;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSharedWishlistItemsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $itemIds = $this->input('item_ids');
        // Гость выбирает не больше позиций, чем может быть в списке
        $maxItems = config('wishlist.max_items');

        return [
            'item_ids' => ['required', 'array', 'min:1', 'max:'.$maxItems],
            'item_ids.*' => ['required', 'ulid'],

            // Совместные подарки: только для позиций, которые выбираются этим же запросом
            'joint_gifts' => ['sometimes', 'array', 'max:'.$maxItems],
            'joint_gifts.*' => ['array:item_id,name,contact,comment'],
            'joint_gifts.*.item_id' => [
                'required',
                'ulid',
                'distinct',
                Rule::in(is_array($itemIds) ? array_filter($itemIds, 'is_string') : []),
            ],
            'joint_gifts.*.name' => ['required', 'string', 'max:50'],
            'joint_gifts.*.contact' => ['nullable', 'string', 'max:100'],
            'joint_gifts.*.comment' => ['nullable', 'string', 'max:200'],
        ];
    }
}
