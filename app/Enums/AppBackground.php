<?php

namespace App\Enums;

/**
 * Фон приложения, который пользователь выбирает в настройках аккаунта.
 *
 * В БД хранится значение (ключ), цвета задаются во фронтенде.
 * При добавлении фона нужно добавить его и в палитру фронтенда.
 */
enum AppBackground: string
{
    case Blossom = 'blossom';
    case Ocean = 'ocean';
    case Mint = 'mint';
    case Sand = 'sand';
    case Mist = 'mist';
    case Stone = 'stone';
    case Cobalt = 'cobalt';
}
