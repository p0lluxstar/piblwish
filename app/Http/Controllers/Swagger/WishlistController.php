<?php

namespace App\Http\Controllers\Swagger;

use App\Enums\WishlistColor;
use App\Enums\WishlistType;
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
                                    property: 'type',
                                    description: 'Тип списка: gift — список желаний, todo — список дел, note — заметка. Список дел и заметка недоступны по общей ссылке',
                                    type: 'string',
                                    enum: WishlistType::class,
                                    example: 'gift'
                                ),

                                new OA\Property(
                                    property: 'title',
                                    description: 'Название; у заметки — null',
                                    type: 'string',
                                    nullable: true,
                                    example: 'День рождения'
                                ),

                                new OA\Property(
                                    property: 'content',
                                    description: 'Текст заметки; у списков желаний и дел — null',
                                    type: 'string',
                                    maxLength: 5000,
                                    nullable: true,
                                    example: null
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
                                    property: 'hideSelections',
                                    description: 'Режим сюрприза: если true, у позиций нет поля isSelected',
                                    type: 'boolean',
                                    example: false
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
                                                property: 'url',
                                                description: 'Ссылка на товар (http или https), необязательна',
                                                type: 'string',
                                                format: 'uri',
                                                maxLength: 2048,
                                                nullable: true,
                                                example: 'https://example.com/books/master-i-margarita'
                                            ),

                                            new OA\Property(
                                                property: 'priority',
                                                description: 'Приоритет: 1 — было бы неплохо, 2 — хочу, 3 — очень хочу; null — не указан',
                                                type: 'integer',
                                                enum: [1, 2, 3],
                                                nullable: true,
                                                example: 3
                                            ),

                                            new OA\Property(
                                                property: 'price',
                                                description: 'Стоимость в целых рублях (0–10 000 000); null — не указана',
                                                type: 'integer',
                                                maximum: 10000000,
                                                minimum: 0,
                                                nullable: true,
                                                example: 1500
                                            ),

                                            new OA\Property(
                                                property: 'isSelected',
                                                description: 'Отсутствует, если включён режим сюрприза (hideSelections)',
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
            properties: [
                new OA\Property(
                    property: 'type',
                    description: 'Тип списка: gift — список желаний, todo — список дел, note — заметка. Необязателен, по умолчанию gift. Задаётся только при создании. У позиций списка дел нельзя указать url, priority и price. У заметки нет title и items, вместо них передаётся content. hideSelections может быть true только у списка желаний',
                    type: 'string',
                    enum: WishlistType::class,
                    example: 'gift'
                ),

                new OA\Property(
                    property: 'title',
                    description: 'Название. Обязательно для списков желаний и дел, у заметки (type = note) запрещено',
                    type: 'string',
                    example: 'День рождения 2'
                ),

                new OA\Property(
                    property: 'content',
                    description: 'Текст заметки до 5000 символов. Обязателен для заметки (type = note), у списков запрещён',
                    type: 'string',
                    maxLength: 5000,
                    example: 'Код домофона 1234'
                ),

                new OA\Property(
                    property: 'color',
                    description: 'Ключ цвета фона списка. Необязателен, по умолчанию white',
                    type: 'string',
                    enum: WishlistColor::class,
                    example: 'mint'
                ),

                new OA\Property(
                    property: 'hideSelections',
                    description: 'Режим сюрприза: скрывать от владельца, какие позиции выбрали гости. Необязателен, по умолчанию true для списка желаний и false для списка дел и заметки',
                    type: 'boolean',
                    example: false
                ),

                new OA\Property(
                    property: 'items',
                    description: 'Позиции списка, хотя бы одна. Обязательны для списков желаний и дел, у заметки запрещены',
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(
                                property: 'label',
                                type: 'string',
                                example: 'Книга «Мастер и Маргарита»'
                            ),

                            new OA\Property(
                                property: 'url',
                                description: 'Ссылка на товар (http или https), необязательна',
                                type: 'string',
                                format: 'uri',
                                maxLength: 2048,
                                nullable: true,
                                example: 'https://example.com/books/master-i-margarita'
                            ),

                            new OA\Property(
                                property: 'priority',
                                description: 'Приоритет: 1 — было бы неплохо, 2 — хочу, 3 — очень хочу; null — не указан',
                                type: 'integer',
                                enum: [1, 2, 3],
                                nullable: true,
                                example: 3
                            ),

                            new OA\Property(
                                property: 'price',
                                description: 'Стоимость в целых рублях (0–10 000 000), необязательна; null — не указана',
                                type: 'integer',
                                maximum: 10000000,
                                minimum: 0,
                                nullable: true,
                                example: 1500
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
                                property: 'type',
                                description: 'Тип списка: gift — список желаний, todo — список дел, note — заметка. Список дел и заметка недоступны по общей ссылке',
                                type: 'string',
                                enum: WishlistType::class,
                                example: 'gift'
                            ),

                            new OA\Property(
                                property: 'title',
                                description: 'Название; у заметки — null',
                                type: 'string',
                                nullable: true,
                                example: 'День рождения 2'
                            ),

                            new OA\Property(
                                property: 'content',
                                description: 'Текст заметки; у списков желаний и дел — null',
                                type: 'string',
                                maxLength: 5000,
                                nullable: true,
                                example: null
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
                                property: 'hideSelections',
                                description: 'Режим сюрприза: если true, у позиций нет поля isSelected',
                                type: 'boolean',
                                example: false
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
                                            property: 'url',
                                            description: 'Ссылка на товар (http или https), необязательна',
                                            type: 'string',
                                            format: 'uri',
                                            maxLength: 2048,
                                            nullable: true,
                                            example: 'https://example.com/books/master-i-margarita'
                                        ),

                                        new OA\Property(
                                            property: 'priority',
                                            description: 'Приоритет: 1 — было бы неплохо, 2 — хочу, 3 — очень хочу; null — не указан',
                                            type: 'integer',
                                            enum: [1, 2, 3],
                                            nullable: true,
                                            example: 3
                                        ),

                                        new OA\Property(
                                            property: 'price',
                                            description: 'Стоимость в целых рублях (0–10 000 000); null — не указана',
                                            type: 'integer',
                                            maximum: 10000000,
                                            minimum: 0,
                                            nullable: true,
                                            example: 1500
                                        ),

                                        new OA\Property(
                                            property: 'isSelected',
                                            description: 'Отсутствует, если включён режим сюрприза (hideSelections)',
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

#[OA\Get(
    path: '/v1/wishlists/{id}/selections',
    summary: 'Получить id позиций, выбранных гостями',
    description: 'Возвращает выбор гостей и в режиме сюрприза. Окно редактирования запрашивает его, когда владелец выключает режим сюрприза, чтобы показать выбор до сохранения списка',
    tags: ['Wishlists'],
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(name: 'id', description: 'ID списка', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
    ],
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
                                property: 'itemIds',
                                type: 'array',
                                items: new OA\Items(type: 'string', format: 'ulid')
                            ),
                        ],
                        type: 'object'
                    ),
                ],
                type: 'object'
            )
        ),

        new OA\Response(
            response: 404,
            description: 'Список не найден'
        ),

        new OA\Response(
            response: 401,
            description: 'Не авторизован'
        ),
    ]
)]

#[OA\Patch(
    path: '/v1/wishlists/{id}/items/{itemId}',
    summary: 'Отметить дело в списке дел выполненным или снять отметку',
    description: 'Только для списков дел (type = todo); для списка желаний возвращается 422',
    tags: ['Wishlists'],
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(name: 'id', description: 'ID списка', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        new OA\Parameter(name: 'itemId', description: 'ID позиции', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
    ],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['isSelected'],
            properties: [
                new OA\Property(
                    property: 'isSelected',
                    description: 'true — дело выполнено',
                    type: 'boolean',
                    example: true
                ),
            ],
            type: 'object'
        )
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Список целиком, в том же формате, что и при изменении списка'
        ),

        new OA\Response(
            response: 404,
            description: 'Список или позиция не найдены'
        ),

        new OA\Response(
            response: 422,
            description: 'Ошибка валидации или список не является списком дел'
        ),

        new OA\Response(
            response: 401,
            description: 'Не авторизован'
        ),
    ]
)]

class WishlistController extends Controller {}
