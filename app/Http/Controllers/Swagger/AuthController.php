<?php

namespace App\Http\Controllers\Swagger;

use App\Http\Controllers\Controller;
use OpenApi\Attributes as OA;


#[OA\Post(
    path: '/api/register',
    summary: 'Регистрация пользователя',
    tags: ['Auth'],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['email', 'password'],
            properties: [
                new OA\Property(
                    property: 'email',
                    type: 'string',
                    example: 'test@example.com'
                ),
                new OA\Property(
                    property: 'password',
                    type: 'string',
                    example: 'password123'
                ),
            ]
        )
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Пользователь зарегистрирован'
        ),
    ]
)]

#[OA\Post(
    path: '/api/verify-registration-code',
    summary: 'Подтверждение кода регистрации',
    tags: ['Auth'],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['email', 'code'],
            properties: [
                new OA\Property(
                    property: 'email',
                    type: 'string',
                    example: 'test@example.com'
                ),
                new OA\Property(
                    property: 'code',
                    type: 'string',
                    example: '1234'
                ),
            ]
        )
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Код подтверждён'
        ),
    ]
)]

#[OA\Post(
    path: '/api/login',
    summary: 'Авторизация',
    tags: ['Auth'],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['email', 'password'],
            properties: [
                new OA\Property(
                    property: 'email',
                    type: 'string',
                    example: 'test@example.com'
                ),
                new OA\Property(
                    property: 'password',
                    type: 'string',
                    example: 'password123'
                ),
            ]
        )
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Успешная авторизация'
        ),
        new OA\Response(
            response: 401,
            description: 'Неверные данные или аккаунт деактивирован'
        ),
        new OA\Response(
            response: 403,
            description: 'Аккаунт не подтверждён (email не подтверждён)'
        ),
    ]
)]

#[OA\Post(
    path: '/v1/forgot-password',
    summary: 'Запрос кода восстановления пароля',
    description: 'Код отправляется только подтверждённому и не удалённому аккаунту. Ответ одинаков для любого email.',
    tags: ['Auth'],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['email'],
            properties: [
                new OA\Property(
                    property: 'email',
                    type: 'string',
                    example: 'test@example.com'
                ),
            ]
        )
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Запрос принят'
        ),
        new OA\Response(
            response: 422,
            description: 'Ошибка валидации'
        ),
        new OA\Response(
            response: 429,
            description: 'Слишком частые запросы (не чаще одного в минуту для email)'
        ),
    ]
)]

#[OA\Post(
    path: '/v1/reset-password',
    summary: 'Установка нового пароля по коду',
    description: 'Код действует 10 минут. После смены пароля все сессии и токены пользователя завершаются.',
    tags: ['Auth'],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['email', 'code', 'password', 'password_confirmation'],
            properties: [
                new OA\Property(
                    property: 'email',
                    type: 'string',
                    example: 'test@example.com'
                ),
                new OA\Property(
                    property: 'code',
                    type: 'string',
                    example: '123456'
                ),
                new OA\Property(
                    property: 'password',
                    type: 'string',
                    example: 'new-password'
                ),
                new OA\Property(
                    property: 'password_confirmation',
                    type: 'string',
                    example: 'new-password'
                ),
            ]
        )
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Пароль изменён'
        ),
        new OA\Response(
            response: 400,
            description: 'Неверный или просроченный код'
        ),
        new OA\Response(
            response: 422,
            description: 'Ошибка валидации'
        ),
        new OA\Response(
            response: 429,
            description: 'Слишком много попыток'
        ),
    ]
)]

#[OA\Post(
    path: '/api/logout',
    summary: 'Выход из системы',
    tags: ['Auth'],
    security: [['bearerAuth' => []]],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Выход выполнен'
        ),
        new OA\Response(
            response: 401,
            description: 'Не авторизован'
        ),
    ]
)]
class AuthController extends Controller {}
