<?php

namespace App\Models;

use App\Enums\WishlistColor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'title', 'color'])]
class Wishlist extends Model
{
    use HasUlids;

    // Совпадает со значением по умолчанию в БД: без этого у только что
    // созданного списка без цвета поле color было бы null до перечитывания
    protected $attributes = [
        'color' => 'white',
    ];

    protected function casts(): array
    {
        return [
            'color' => WishlistColor::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        // Позиции всегда отдаются в порядке, заданном владельцем списка
        return $this->hasMany(WishlistItem::class)->orderBy('position');
    }
}
