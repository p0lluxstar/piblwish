<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Чужой список, добавленный пользователем к себе с общей страницы. share_token —
// токен ссылки, по которой список добавили: после смены ссылки владельцем
// закладка недоступна (isAvailable)
#[Fillable(['user_id', 'wishlist_id', 'share_token'])]
class SavedWishlist extends Model
{
    use HasUlids;

    protected static function booted(): void
    {
        // Без явного токена закладка запоминает текущую ссылку списка
        static::creating(function (SavedWishlist $saved) {
            $saved->share_token ??= Wishlist::query()->whereKey($saved->wishlist_id)->value('share_token');
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function wishlist(): BelongsTo
    {
        return $this->belongsTo(Wishlist::class);
    }

    // Открывается ли список по ссылке, по которой его добавили
    public function isAvailable(): bool
    {
        return $this->wishlist->isOpenByLink()
            && $this->wishlist->share_token === $this->share_token;
    }
}
