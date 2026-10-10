<?php

namespace App\Http\Controllers\Swagger;

use App\Enums\WishlistColor;
use App\Http\Controllers\Controller;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Saved wishlists',
    description: 'Чужие списки, добавленные пользователем к себе с общей страницы'
)]

#[OA\Schema(
    schema: 'SavedWishlist',
    description: 'Сводка чужого списка. Если владелец закрыл доступ к списку дел, available = false и поля списка равны null',
    properties: [
        new OA\Property(property: 'id', description: 'ID самого списка', type: 'string', example: '01jq3v7x8k2m4n6p8r0t2w4y6z'),
        new OA\Property(property: 'available', description: 'Открывается ли список по ссылке', type: 'boolean', example: true),
        new OA\Property(property: 'savedAt', description: 'Когда список добавлен', type: 'string', format: 'date-time', example: '2026-10-10T09:15:00+00:00'),
        new OA\Property(property: 'username', description: 'Имя владельца', type: 'string', nullable: true, example: 'anna'),
        new OA\Property(property: 'avatarUrl', description: 'Фотография владельца; null — показывается первая буква имени', type: 'string', nullable: true, example: null),
        new OA\Property(property: 'type', type: 'string', enum: ['gift', 'todo'], nullable: true, example: 'gift'),
        new OA\Property(property: 'title', type: 'string', nullable: true, example: 'День рождения'),
        new OA\Property(property: 'color', type: 'string', enum: WishlistColor::class, nullable: true, example: 'mint'),
        new OA\Property(property: 'dueDate', description: 'Срок списка дел или дата события списка желаний (Y-m-d)', type: 'string', format: 'date', nullable: true, example: '2026-12-31'),
        new OA\Property(property: 'itemsCount', description: 'Число позиций', type: 'integer', nullable: true, example: 5),
        new OA\Property(property: 'selectedCount', description: 'Выбранные гостями подарки или выполненные дела', type: 'integer', nullable: true, example: 2),
    ],
    type: 'object'
)]

#[OA\Get(
    path: '/v1/saved-wishlists',
    summary: 'Чужие списки пользователя, последние добавленные — первыми',
    tags: ['Saved wishlists'],
    security: [['bearerAuth' => []]],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Успешный ответ',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'statusCode', type: 'integer', example: 200),
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/SavedWishlist')),
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
    path: '/v1/saved-wishlists/{id}',
    summary: 'Добавить чужой список к себе',
    description: 'Добавить можно список желаний или список дел с доступом по ссылке. Повторное добавление возвращает прежнюю запись',
    tags: ['Saved wishlists'],
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(name: 'id', description: 'ID списка', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Список добавлен',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'statusCode', type: 'integer', example: 200),
                    new OA\Property(property: 'data', ref: '#/components/schemas/SavedWishlist'),
                ],
                type: 'object'
            )
        ),

        new OA\Response(
            response: 404,
            description: 'Список не найден или не открывается по ссылке (заметка, закрытый список дел)'
        ),

        new OA\Response(
            response: 422,
            description: 'Свой список нельзя добавить в чужие'
        ),

        new OA\Response(
            response: 401,
            description: 'Не авторизован'
        ),
    ]
)]

#[OA\Delete(
    path: '/v1/saved-wishlists/{id}',
    summary: 'Убрать список из чужих',
    description: 'Отсутствующая запись не считается ошибкой',
    tags: ['Saved wishlists'],
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(name: 'id', description: 'ID списка', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Список убран',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'statusCode', type: 'integer', example: 200),
                    new OA\Property(
                        property: 'data',
                        properties: [new OA\Property(property: 'id', type: 'string', example: '01jq3v7x8k2m4n6p8r0t2w4y6z')],
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

class SavedWishlistController extends Controller {}
