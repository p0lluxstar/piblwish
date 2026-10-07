<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'new_email', 'code_hash', 'attempts', 'expires_at'])]
class EmailChangeRequest extends Model
{
    use HasUlids;

    /**
     * Преобразование атрибутов к типам.
     */
    protected $casts = [
        'attempts' => 'integer',
        'expires_at' => 'datetime',
    ];

    // Запрос на смену email принадлежит конкретному пользователю
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Срок действия кода истёк
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
