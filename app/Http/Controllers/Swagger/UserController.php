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
