<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Бронь гостя: позиции, выбранные за одно сохранение на общей странице.
 * Сам токен не хранится, только его SHA-256 (см. hashToken).
 */
#[Fillable(['wishlist_id', 'token_hash'])]
class WishlistReservation extends Model
{
    use HasUlids;

    // Токен длинный и случайный, поэтому достаточно быстрого хеша:
    // перебор по нему невозможен, а поиск брони идёт по индексу
    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public function wishlist(): BelongsTo
    {
        return $this->belongsTo(Wishlist::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(WishlistItem::class, 'reservation_id');
    }
}
