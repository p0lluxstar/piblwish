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
    // созданного списка без цвета или типа поле было бы null до перечитывания
    protected $attributes = [
        'type' => 'gift',
        'color' => 'white',
        'hide_selections' => false,
        'is_shared' => false,
        'guests_can_check' => false,
        'guest_name_required' => true,
    ];

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

    // Список дел: у позиций нет ссылки, цены и приоритета; по ссылке доступен,
    // только если владелец включил is_shared, а отмечать дела гости могут
    // лишь при guests_can_check
    public function isTodo(): bool
    {
        return $this->type === WishlistType::Todo;
    }

    // Заметка: доступна только владельцу, вместо названия и позиций — текст в content
    public function isNote(): bool
    {
        return $this->type === WishlistType::Note;
    }

    // Открывается ли список по общей ссылке: список желаний — всегда,
    // список дел — если владелец включил доступ, заметка — никогда
    public function isShared(): bool
    {
        return $this->isGift() || ($this->isTodo() && $this->is_shared);
    }

    // То же условие, что в isShared(), для запроса: списки желаний
    // и списки дел с включённым доступом по ссылке
    #[Scope]
    protected function openByLink(Builder $query): void
    {
        $query->where(fn (Builder $query) => $query
            ->where('type', WishlistType::Gift)
            ->orWhere(fn (Builder $query) => $query
                ->where('type', WishlistType::Todo)
                ->where('is_shared', true)));
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
