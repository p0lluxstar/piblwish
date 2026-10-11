<?php

namespace App\Http\Requests\SharedWishlist;

use App\Services\SharedWishlist\SharedWishlistService;
use Illuminate\Foundation\Http\FormRequest;

class CancelReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'size:'.SharedWishlistService::RESERVATION_TOKEN_LENGTH],
            // Позиции брони, выбор которых отменяется; остальные остаются за гостем
            'item_ids' => ['required', 'array', 'min:1'],
            'item_ids.*' => ['required', 'ulid'],
        ];
    }
}
