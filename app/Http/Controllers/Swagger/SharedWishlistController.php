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
        new OA\Response(response: 422, description: 'Позиция уже выбрана другим гостем'),
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
    description: 'Остальные позиции брони остаются выбранными. Бронь без позиций удаляется',
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

class SharedWishlistController extends Controller {}
