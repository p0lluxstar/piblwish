<?php

namespace App\Http\Requests\SharedWishlist;

use App\Services\SharedWishlist\SharedWishlistService;
use Illuminate\Foundation\Http\FormRequest;

class GetReservationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Токены броней, сохранённые в браузере гостя
            'tokens' => ['required', 'array', 'min:1', 'max:20'],
            'tokens.*' => ['required', 'string', 'size:'.SharedWishlistService::RESERVATION_TOKEN_LENGTH],
        ];
    }
}
