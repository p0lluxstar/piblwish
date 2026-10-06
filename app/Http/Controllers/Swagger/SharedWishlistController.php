<?php

namespace App\Http\Controllers\Swagger;

use App\Http\Controllers\Controller;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Shared wishlists',
    description: 'Общая страница списка: выбор подарков гостями и отмена выбора'
)]

#[OA\Schema(
    schema: 'SharedWishlistReservation',
    description: 'Бронь гостя: позиции, выбранные за одно сохранение',
    properties: [
        new OA\Property(
            property: 'token',
            description: 'Токен брони (40 символов). Сервер хранит только его хеш, повторно токен не выдаётся',
            type: 'string',
            example: 'q3VZ8sTn1bK0yXcR5mWp7hLd2fGj9aEu4oNi6vQs'
        ),

        new OA\Property(
            property: 'itemIds',
            description: 'Позиции, которые входят в бронь',
            type: 'array',
            items: new OA\Items(type: 'string', format: 'ulid')
        ),
    ],
    type: 'object'
)]

#[OA\Schema(
    schema: 'SharedWishlistJointGift',
    description: 'Совместный подарок: данные организатора для других гостей. В ответы владельцу списка не попадает',
    required: ['name'],
    properties: [
        new OA\Property(property: 'name', type: 'string', maxLength: 50, example: 'Иван, коллега Ани'),
        new OA\Property(
            property: 'contact',
            description: 'Контакт организатора; выводится обычным текстом, без ссылки',
            type: 'string',
            maxLength: 100,
            nullable: true,
            example: '@ivan_k'
        ),
        new OA\Property(property: 'comment', type: 'string', maxLength: 200, nullable: true, example: 'Собираем до 10 октября'),
    ],
    type: 'object'
)]

#[OA\Get(
    path: '/api/v1/shared-wishlists/{id}',
    summary: 'Список желаний для гостя',
    description: 'У каждой позиции есть поле jointGift: данные совместного подарка или null',
    tags: ['Shared wishlists'],
    parameters: [
        new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'ulid')),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Список желаний',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(
                        property: 'data',
                        properties: [
                            new OA\Property(property: 'id', type: 'string', format: 'ulid'),
                            new OA\Property(property: 'title', type: 'string'),
                            new OA\Property(property: 'username', type: 'string', nullable: true),
                            new OA\Property(
                                property: 'items',
                                type: 'array',
                                items: new OA\Items(
                                    properties: [
                                        new OA\Property(property: 'id', type: 'string', format: 'ulid'),
                                        new OA\Property(property: 'isSelected', type: 'boolean'),
                                        new OA\Property(property: 'label', type: 'string'),
                                        new OA\Property(property: 'url', type: 'string', nullable: true),
                                        new OA\Property(property: 'priority', type: 'integer', nullable: true),
                                        new OA\Property(property: 'price', type: 'integer', nullable: true),
                                        new OA\Property(
                                            property: 'jointGift',
                                            ref: '#/components/schemas/SharedWishlistJointGift',
                                            nullable: true
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
        new OA\Response(response: 404, description: 'Список не найден или не является списком желаний'),
        new OA\Response(response: 429, description: 'Слишком много запросов'),
    ]
)]

#[OA\Patch(
    path: '/api/v1/shared-wishlists/{wishlist}/items',
    summary: 'Выбрать подарки и получить токен брони',
    tags: ['Shared wishlists'],
    parameters: [
        new OA\Parameter(name: 'wishlist', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'ulid')),
    ],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['item_ids'],
            properties: [
                new OA\Property(
                    property: 'item_ids',
                    type: 'array',
                    items: new OA\Items(type: 'string', format: 'ulid')
                ),
                new OA\Property(
                    property: 'joint_gifts',
                    description: 'Совместные подарки; item_id каждого должен входить в item_ids',
                    type: 'array',
                    items: new OA\Items(
                        required: ['item_id', 'name'],
                        properties: [
                            new OA\Property(property: 'item_id', type: 'string', format: 'ulid'),
                            new OA\Property(property: 'name', type: 'string', maxLength: 50),
                            new OA\Property(property: 'contact', type: 'string', maxLength: 100, nullable: true),
                            new OA\Property(property: 'comment', type: 'string', maxLength: 200, nullable: true),
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
            response: 200,
            description: 'Позиции выбраны. В data.reservation — токен для отмены выбора',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(
                        property: 'data',
                        properties: [
                            new OA\Property(property: 'reservation', ref: '#/components/schemas/SharedWishlistReservation'),
                        ],
                        type: 'object'
                    ),
                ],
                type: 'object'
            )
        ),
        new OA\Response(response: 422, description: 'Позиция уже выбрана другим гостем или совместный подарок указан для невыбранной позиции'),
        new OA\Response(response: 429, description: 'Слишком много запросов'),
    ]
)]

#[OA\Post(
    path: '/api/v1/shared-wishlists/{wishlist}/reservations/lookup',
    summary: 'Позиции броней по токенам',
    description: 'Неизвестные токены и брони без позиций в ответ не попадают',
    tags: ['Shared wishlists'],
    parameters: [
        new OA\Parameter(name: 'wishlist', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'ulid')),
    ],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['tokens'],
            properties: [
                new OA\Property(
                    property: 'tokens',
                    type: 'array',
                    maxItems: 20,
                    items: new OA\Items(type: 'string', maxLength: 40, minLength: 40)
                ),
            ],
            type: 'object'
        )
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Найденные брони',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(
                        property: 'data',
                        type: 'array',
                        items: new OA\Items(ref: '#/components/schemas/SharedWishlistReservation')
                    ),
                ],
                type: 'object'
            )
        ),
        new OA\Response(response: 404, description: 'Список не найден'),
        new OA\Response(response: 422, description: 'Ошибка валидации'),
    ]
)]

#[OA\Post(
    path: '/api/v1/shared-wishlists/{wishlist}/reservations/cancel',
    summary: 'Отменить выбор позиций брони',
    description: 'Остальные позиции брони остаются выбранными. Совместные подарки отменённых позиций удаляются. Бронь без позиций удаляется',
    tags: ['Shared wishlists'],
    parameters: [
        new OA\Parameter(name: 'wishlist', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'ulid')),
    ],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['token', 'item_ids'],
            properties: [
                new OA\Property(property: 'token', type: 'string', maxLength: 40, minLength: 40),
                new OA\Property(
                    property: 'item_ids',
                    type: 'array',
                    items: new OA\Items(type: 'string', format: 'ulid')
                ),
            ],
            type: 'object'
        )
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Выбор отменён. В data.reservation — оставшиеся позиции брони',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(
                        property: 'data',
                        properties: [
                            new OA\Property(property: 'reservation', ref: '#/components/schemas/SharedWishlistReservation'),
                        ],
                        type: 'object'
                    ),
                ],
                type: 'object'
            )
        ),
        new OA\Response(response: 404, description: 'Бронь не найдена'),
        new OA\Response(response: 422, description: 'Позиция не входит в бронь'),
        new OA\Response(response: 429, description: 'Слишком много запросов'),
    ]
)]

#[OA\Put(
    path: '/api/v1/shared-wishlists/{wishlist}/items/{item}/joint-gift',
    summary: 'Изменить, добавить или убрать совместный подарок',
    description: 'Доступно организатору по токену брони, в которую входит позиция. Выбор позиции не меняется',
    tags: ['Shared wishlists'],
    parameters: [
        new OA\Parameter(name: 'wishlist', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'ulid')),
        new OA\Parameter(name: 'item', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'ulid')),
    ],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['token', 'joint_gift'],
            properties: [
                new OA\Property(property: 'token', type: 'string', maxLength: 40, minLength: 40),
                new OA\Property(
                    property: 'joint_gift',
                    description: 'Новые данные; null убирает совместный подарок',
                    ref: '#/components/schemas/SharedWishlistJointGift',
                    nullable: true
                ),
            ],
            type: 'object'
        )
    ),
    responses: [
        new OA\Response(response: 200, description: 'Совместный подарок изменён; в ответе — список для гостя'),
        new OA\Response(response: 404, description: 'Бронь не найдена'),
        new OA\Response(response: 422, description: 'Позиция не входит в бронь или данные не прошли проверку'),
        new OA\Response(response: 429, description: 'Слишком много запросов'),
    ]
)]

class SharedWishlistController extends Controller {}
