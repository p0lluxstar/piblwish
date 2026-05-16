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
            description: 'Неверные данные'
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
