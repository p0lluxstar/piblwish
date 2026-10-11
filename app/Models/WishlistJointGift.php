<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Совместный подарок: гость-организатор выбрал позицию и предлагает
 * остальным гостям подарить её вместе. Данные видны только гостям,
 * в ответы владельцу списка они не попадают.
 */
#[Fillable(['item_id', 'reservation_id', 'organizer_name', 'contact', 'comment'])]
class WishlistJointGift extends Model
{
    use HasUlids;

    public function item(): BelongsTo
    {
        return $this->belongsTo(WishlistItem::class, 'item_id');
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(WishlistReservation::class, 'reservation_id');
    }
}
