<?php

namespace App\Models;

use App\Enums\WishlistItemPriority;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'wishlist_id',
    'description',
    'url',
    'priority',
    'price',
    'is_selected',
    'reservation_id',
    'position',
])]
class WishlistItem extends Model
{
    use HasUlids;

    protected function casts(): array
    {
        return [
            'is_selected' => 'boolean',
            'position' => 'integer',
            'priority' => WishlistItemPriority::class,
            'price' => 'integer',
        ];
    }

    public function wishlist(): BelongsTo
    {
        return $this->belongsTo(Wishlist::class);
    }

    // Бронь гостя; null, если позицию отметил владелец или её выбрали до появления броней
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(WishlistReservation::class, 'reservation_id');
    }
}
