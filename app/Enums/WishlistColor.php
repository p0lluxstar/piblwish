<?php

namespace App\Enums;

/**
 * Цвет фона списка.
 *
 * В БД хранится значение (ключ), оттенки задаются во фронтенде.
 * При добавлении цвета нужно добавить его и в палитру фронтенда.
 */
enum WishlistColor: string
{
    case White = 'white';
    case Lavender = 'lavender';
    case Pink = 'pink';
    case Peach = 'peach';
    case Lemon = 'lemon';
    case Mint = 'mint';
    case Sky = 'sky';
}
