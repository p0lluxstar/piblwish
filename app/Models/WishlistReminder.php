<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

// Отправленное напоминание о сроке списка; защищает от повторной отправки
#[Fillable(['user_id', 'wishlist_id', 'due_date'])]
class WishlistReminder extends Model
{
    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
        ];
    }
}
