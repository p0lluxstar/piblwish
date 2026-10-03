<?php

namespace App\Http\Controllers\Swagger;

use App\Enums\WishlistColor;
use App\Http\Controllers\Controller;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Wishlists',
    description: 'Работа со списками желаний'
)]

#[OA\Get(
    path: '/v1/wishlists',
    summary: 'Получить списки желаний пользователя',
    tags: ['Wishlists'],
    security: [['bearerAuth' => []]],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Успешный ответ',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(
                        property: 'data',
                        type: 'array',
                        items: new OA\Items(
                            properties: [
                                new OA\Property(
                                    property: 'id',
                                    type: 'integer',
                                    example: 1
                                ),

                                new OA\Property(
                                    property: 'title',
                                    type: 'string',
                                    example: 'День рождения'
                                ),

                                new OA\Property(
                                    property: 'color',
                                    description: 'Ключ цвета фона списка',
                                    type: 'string',
                                    enum: WishlistColor::class,
                                    example: 'lavender'
                                ),

                                new OA\Property(
                                    property: 'createdAt',
                                    description: 'Дата создания списка (ISO 8601)',
                                    type: 'string',
                                    format: 'date-time',
                                    example: '2026-09-12T14:30:00+00:00'
                                ),

                                new OA\Property(
                                    property: 'items',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(
                                                property: 'id',
                                                type: 'integer',
                                                example: 10
                                            ),

                                            new OA\Property(
                                                property: 'label',
                                                type: 'string',
                                                example: 'Книга «Мастер и Маргарита»'
                                            ),

                                            new OA\Property(
                                                property: 'isSelected',
                                                type: 'boolean',
                                                example: false
                                            ),
                                        ],
                                        type: 'object'
                                    )
                                ),
                            ],
                            type: 'object'
                        )
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

#[OA\Post(
    path: '/v1/wishlists',
    summary: 'Создать список желаний',
    tags: ['Wishlists'],
    security: [['bearerAuth' => []]],

    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['title', 'items'],
            properties: [
                new OA\Property(
                    property: 'title',
                    type: 'string',
                    example: 'День рождения 2'
                ),

                new OA\Property(
                    property: 'color',
                    description: 'Ключ цвета фона списка. Необязателен, по умолчанию white',
                    type: 'string',
                    enum: WishlistColor::class,
                    example: 'mint'
                ),

                new OA\Property(
                    property: 'items',
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(
                                property: 'label',
                                type: 'string',
                                example: 'Книга «Мастер и Маргарита»'
                            ),
                        ],
                        type: 'object'
                    )
                ),
            ],
            type: 'object'
        )
    ),

    responses: [
        new OA\Response(
            response: 201,
            description: 'Список успешно создан',
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
                                property: 'title',
                                type: 'string',
                                example: 'День рождения 2'
                            ),

                            new OA\Property(
                                property: 'color',
                                description: 'Ключ цвета фона списка',
                                type: 'string',
                                enum: WishlistColor::class,
                                example: 'mint'
                            ),

                            new OA\Property(
                                property: 'createdAt',
                                description: 'Дата создания списка (ISO 8601)',
                                type: 'string',
                                format: 'date-time',
                                example: '2026-09-12T14:30:00+00:00'
                            ),

                            new OA\Property(
                                property: 'items',
                                type: 'array',
                                items: new OA\Items(
                                    properties: [
                                        new OA\Property(
                                            property: 'id',
                                            type: 'integer',
                                            example: 1
                                        ),

                                        new OA\Property(
                                            property: 'label',
                                            type: 'string',
                                            example: 'Свеча с ароматом ванили'
                                        ),

                                        new OA\Property(
                                            property: 'isSelected',
                                            type: 'boolean',
                                            example: false
                                        ),
                                    ],
                                    type: 'object'
                                )
                            ),
                        ],
                        type: 'object'
                    ),
                ],
                type: 'object'
            )
        ),

        new OA\Response(
            response: 422,
            description: 'Ошибка валидации'
        ),

        new OA\Response(
            response: 401,
            description: 'Не авторизован'
        ),
    ]
)]

class WishlistController extends Controller {}
