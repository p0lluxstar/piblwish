<?php

namespace App\Http\Requests\SharedWishlist;

use App\Services\SharedWishlist\SharedWishlistService;
use Illuminate\Foundation\Http\FormRequest;

class UpdateJointGiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Токен брони, в которую входит позиция: изменить совместный подарок может только организатор
            'token' => ['required', 'string', 'size:'.SharedWishlistService::RESERVATION_TOKEN_LENGTH],
            // Новые данные совместного подарка; null — позиция остаётся выбранной, но уже не вместе
            'joint_gift' => ['present', 'nullable', 'array:name,contact,comment'],
            'joint_gift.name' => ['required_with:joint_gift', 'string', 'max:50'],
            'joint_gift.contact' => ['nullable', 'string', 'max:100'],
            'joint_gift.comment' => ['nullable', 'string', 'max:200'],
        ];
    }
}
