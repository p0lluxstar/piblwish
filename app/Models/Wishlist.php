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

#[Fillable(['user_id', 'type', 'title', 'content', 'color', 'hide_selections', 'is_shared', 'guests_can_check', 'guest_name_required', 'due_date', 'archived_at'])]
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
        // Список желаний и сбор по умолчанию открыты по ссылке, список дел и заметка — нет
        static::creating(function (Wishlist $wishlist) {
            $wishlist->is_shared ??= $wishlist->isGift() || $wishlist->isFund();
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
            'archived_at' => 'datetime',
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

    // Сбор: позиции — цели со ссылкой на стороннюю платформу, гости по ним только переходят
    public function isFund(): bool
    {
        return $this->type === WishlistType::Fund;
    }

    // Находится ли список в архиве: владелец видит его только для просмотра,
    // по ссылке он не открывается
    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    // Включён ли у списка доступ по общей ссылке: у списка желаний — если владелец
    // его не закрыл, у списка дел — если включил, у заметки — никогда. Это настройка
    // владельца; открывается ли список сейчас, показывает isOpenByLink()
    public function isShared(): bool
    {
        return ! $this->isNote() && $this->is_shared;
    }

    // Открывается ли список по общей ссылке: доступ включён и список не в архиве
    public function isOpenByLink(): bool
    {
        return $this->isShared() && ! $this->isArchived();
    }

    // То же условие, что в isOpenByLink(), для запроса
    #[Scope]
    protected function openByLink(Builder $query): void
    {
        $query->where('type', '!=', WishlistType::Note)
            ->where('is_shared', true)
            ->whereNull('archived_at');
    }

    // Списки, которые не в архиве
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereNull('archived_at');
    }

    // Списки в архиве
    #[Scope]
    protected function archived(Builder $query): void
    {
        $query->whereNotNull('archived_at');
    }

    // Могут ли гости по ссылке отмечать дела выполненными: только в списке дел,
    // открытом по ссылке, и только если владелец это разрешил
    public function guestsCanCheck(): bool
    {
        return $this->isTodo() && $this->isOpenByLink() && $this->guests_can_check;
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
