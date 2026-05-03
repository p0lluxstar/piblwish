<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'code_hash', 'expires_at', 'used_at'])]
class VerificationRegistrationCode extends Model
{
    use HasUlids;

    /**
     * Преобразование атрибутов к типам.
     */
    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    // Код подтверждения принадлежит конкретному пользователю
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
