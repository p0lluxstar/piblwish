<?php

namespace App\Enums;

/**
 * Тип списка.
 *
 * Gift — список желаний: позиции выбирают гости по ссылке.
 * Todo — список дел: доступен только владельцу, is_selected позиции
 * означает «выполнено», а ссылка, цена, приоритет и режим сюрприза не используются.
 */
enum WishlistType: string
{
    case Gift = 'gift';
    case Todo = 'todo';
}
