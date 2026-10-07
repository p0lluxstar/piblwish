<?php

namespace App\Http\Controllers\Swagger;

use App\Http\Controllers\Controller;
use OpenApi\Attributes as OA;

#[OA\Get(
    path: '/api/user',
    summary: 'Получить текущего пользователя',
    tags: ['User'],
    security: [['bearerAuth' => []]],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Успешный ответ',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(
                        property: 'data',
                        properties: [
                            new OA\Property(
                                property: 'id',
                                type: 'integer',
                                example: 1
                            ),
                            new OA\Property(
                                property: 'username',
                                type: 'string',
                                example: 'john_doe'
                            ),
                            new OA\Property(
                                property: 'email',
                                type: 'string',
                                example: 'john@example.com'
                            ),
                            new OA\Property(
                                property: 'background',
                                description: 'Фон приложения',
                                type: 'string',
                                enum: ['blossom', 'ocean', 'mint', 'sand', 'mist', 'stone', 'cobalt'],
                                example: 'blossom'
                            ),
                        ],
                        type: 'object'
                    ),
                ],
                type: 'object'
            )
        ),

        new OA\Response(
            response: 401,
            description: 'Не авторизован'
        ),
    ]
)]

#[OA\Patch(
    path: '/v1/user',
    summary: 'Изменить настройки текущего пользователя',
    description: 'Сохраняет фон приложения, выбранный пользователем. Фон по умолчанию — blossom. Возвращает пользователя в том же виде, что GET /v1/user.',
    tags: ['User'],
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['background'],
            properties: [
                new OA\Property(
                    property: 'background',
                    type: 'string',
                    enum: ['blossom', 'ocean', 'mint', 'sand', 'mist', 'stone', 'cobalt'],
                    example: 'ocean'
                ),
            ]
        )
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Настройки сохранены',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(
                        property: 'success',
                        type: 'boolean',
                        example: true
                    ),
                    new OA\Property(
                        property: 'statusCode',
                        type: 'integer',
                        example: 200
                    ),
                    new OA\Property(
                        property: 'data',
                        properties: [
                            new OA\Property(property: 'id', type: 'string', example: '01jq3v7x8k2m4n6p8r0t2w4y6z'),
                            new OA\Property(property: 'username', type: 'string', example: 'john_doe'),
                            new OA\Property(property: 'email', type: 'string', example: 'john@example.com'),
                            new OA\Property(property: 'background', type: 'string', example: 'ocean'),
                        ],
                        type: 'object'
                    ),
                ],
                type: 'object'
            )
        ),

        new OA\Response(
            response: 401,
            description: 'Не авторизован'
        ),

        new OA\Response(
            response: 422,
            description: 'Ошибка валидации: фон не передан или такого фона нет',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: false),
                    new OA\Property(property: 'statusCode', type: 'integer', example: 422),
                    new OA\Property(
                        property: 'data',
                        properties: [
                            new OA\Property(property: 'message', type: 'string', example: 'Недопустимый фон'),
                            new OA\Property(
                                property: 'errors',
                                type: 'object',
                                example: ['background' => ['Недопустимый фон']]
                            ),
                        ],
                        type: 'object'
                    ),
                ],
                type: 'object'
            )
        ),

        new OA\Response(
            response: 429,
            description: 'Слишком много запросов'
        ),
    ]
)]

#[OA\Put(
    path: '/v1/user/password',
    summary: 'Сменить пароль текущего пользователя',
    description: 'Проверяет текущий пароль и сохраняет новый. Пользователь остаётся в системе, его сессии на других устройствах и Sanctum-токены удаляются. Лимит: 5 запросов в минуту и 20 в час.',
    tags: ['User'],
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['current_password', 'password', 'password_confirmation'],
            properties: [
                new OA\Property(
                    property: 'current_password',
                    type: 'string',
                    example: 'password123'
                ),
                new OA\Property(
                    property: 'password',
                    description: 'Минимум 8 символов, должен отличаться от текущего',
                    type: 'string',
                    example: 'newPassword123'
                ),
                new OA\Property(
                    property: 'password_confirmation',
                    type: 'string',
                    example: 'newPassword123'
                ),
            ]
        )
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Пароль изменён',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(
                        property: 'success',
                        type: 'boolean',
                        example: true
                    ),
                    new OA\Property(
                        property: 'statusCode',
                        type: 'integer',
                        example: 200
                    ),
                    new OA\Property(
                        property: 'data',
                        properties: [
                            new OA\Property(
                                property: 'message',
                                type: 'string',
                                example: 'Пароль изменён'
                            ),
                        ],
                        type: 'object'
                    ),
                ],
                type: 'object'
            )
        ),

        new OA\Response(
            response: 401,
            description: 'Не авторизован'
        ),

        new OA\Response(
            response: 422,
            description: 'Ошибка валидации: неверный текущий пароль, пароли не совпадают, новый пароль короче 8 символов или совпадает с текущим',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(
                        property: 'success',
                        type: 'boolean',
                        example: false
                    ),
                    new OA\Property(
                        property: 'statusCode',
                        type: 'integer',
                        example: 422
                    ),
                    new OA\Property(
                        property: 'data',
                        properties: [
                            new OA\Property(
                                property: 'message',
                                type: 'string',
                                example: 'Неверный текущий пароль'
                            ),
                            new OA\Property(
                                property: 'errors',
                                type: 'object',
                                example: [
                                    'current_password' => ['Неверный текущий пароль'],
                                ]
                            ),
                        ],
                        type: 'object'
                    ),
                ],
                type: 'object'
            )
        ),

        new OA\Response(
            response: 429,
            description: 'Слишком много запросов'
        ),
    ]
)]

#[OA\Delete(
    path: '/v1/user',
    summary: 'Удалить аккаунт текущего пользователя',
    description: 'Деактивирует аккаунт (проставляет deactivated_at), удаляет вишлисты пользователя и их позиции, завершает все его сессии. Войти в деактивированный аккаунт нельзя.',
    tags: ['User'],
    security: [['bearerAuth' => []]],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Аккаунт удалён',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(
                        property: 'success',
                        type: 'boolean',
                        example: true
                    ),
                    new OA\Property(
                        property: 'statusCode',
                        type: 'integer',
                        example: 200
                    ),
                    new OA\Property(
                        property: 'data',
                        properties: [
                            new OA\Property(
                                property: 'message',
                                type: 'string',
                                example: 'Аккаунт удалён'
                            ),
                        ],
                        type: 'object'
                    ),
                ],
                type: 'object'
            )
        ),

        new OA\Response(
            response: 401,
            description: 'Не авторизован'
        ),

        new OA\Response(
            response: 429,
            description: 'Слишком много запросов'
        ),
    ]
)]
class UserController extends Controller {}
