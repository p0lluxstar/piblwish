<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'wishlist_id',
    'description',
    'is_selected'
])]
class WishlistItem extends Model
{
    use HasUlids;

    protected function casts(): array
    {
        return [
            'is_selected' => 'boolean',
        ];
    }

    public function wishlist(): BelongsTo
    {
        return $this->belongsTo(Wishlist::class);
    }
}
