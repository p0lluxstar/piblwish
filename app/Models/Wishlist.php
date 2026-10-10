<?php

namespace App\Models;

use App\Enums\WishlistColor;
use App\Enums\WishlistType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'type', 'title', 'content', 'color', 'hide_selections', 'is_shared', 'guests_can_check', 'guest_name_required', 'due_date'])]
class Wishlist extends Model
{
    use HasUlids;

    // Совпадает со значениями по умолчанию в БД: без этого у только что
    // созданного списка без цвета или типа поле было бы null до перечитывания.
    // is_shared здесь нет: его значение по умолчанию зависит от типа (booted)
    protected $attributes = [
        'type' => 'gift',
        'color' => 'white',
        'hide_selections' => false,
        'guests_can_check' => false,
        'guest_name_required' => true,
    ];

    protected static function booted(): void
    {
        // Список желаний по умолчанию открыт по ссылке, список дел и заметка — нет
        static::creating(function (Wishlist $wishlist) {
            $wishlist->is_shared ??= $wishlist->isGift();
        });
    }

    protected function casts(): array
    {
        return [
            'type' => WishlistType::class,
            'color' => WishlistColor::class,
            'hide_selections' => 'boolean',
            'is_shared' => 'boolean',
            'guests_can_check' => 'boolean',
            'guest_name_required' => 'boolean',
            // Только дата, без времени: срок списка дел или дата события списка желаний
            'due_date' => 'date',
        ];
    }

    // Список желаний: позиции выбирают гости по ссылке
    public function isGift(): bool
    {
        return $this->type === WishlistType::Gift;
    }

    // Список дел: у позиций нет ссылки, цены и приоритета; отмечать дела
    // по ссылке гости могут лишь при guests_can_check
    public function isTodo(): bool
    {
        return $this->type === WishlistType::Todo;
    }

    // Заметка: доступна только владельцу, вместо названия и позиций — текст в content
    public function isNote(): bool
    {
        return $this->type === WishlistType::Note;
    }

    // Открывается ли список по общей ссылке: список желаний или дел — если
    // владелец не закрыл (у желаний) или включил (у дел) доступ, заметка — никогда
    public function isShared(): bool
    {
        return ! $this->isNote() && $this->is_shared;
    }

    // То же условие, что в isShared(), для запроса
    #[Scope]
    protected function openByLink(Builder $query): void
    {
        $query->where('type', '!=', WishlistType::Note)
            ->where('is_shared', true);
    }

    // Могут ли гости по ссылке отмечать дела выполненными: только в списке дел,
    // открытом по ссылке, и только если владелец это разрешил
    public function guestsCanCheck(): bool
    {
        return $this->isTodo() && $this->is_shared && $this->guests_can_check;
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
