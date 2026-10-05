<?php

namespace App\Models;

use App\Enums\WishlistColor;
use App\Enums\WishlistType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'type', 'title', 'content', 'color', 'hide_selections'])]
class Wishlist extends Model
{
    use HasUlids;

    // Совпадает со значениями по умолчанию в БД: без этого у только что
    // созданного списка без цвета или типа поле было бы null до перечитывания
    protected $attributes = [
        'type' => 'gift',
        'color' => 'white',
        'hide_selections' => false,
    ];

    protected function casts(): array
    {
        return [
            'type' => WishlistType::class,
            'color' => WishlistColor::class,
            'hide_selections' => 'boolean',
        ];
    }

    // Список дел: доступен только владельцу, у позиций нет ссылки, цены и приоритета
    public function isTodo(): bool
    {
        return $this->type === WishlistType::Todo;
    }

    // Заметка: доступна только владельцу, вместо названия и позиций — текст в content
    public function isNote(): bool
    {
        return $this->type === WishlistType::Note;
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

    // Брони гостей, по которым они могут отменить свой выбор
    public function reservations(): HasMany
    {
        return $this->hasMany(WishlistReservation::class);
    }
}
