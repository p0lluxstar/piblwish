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
                                    description: 'Тип списка: gift — список желаний, todo — список дел, note — заметка. Список желаний и список дел открываются по общей ссылке только при isShared = true, отмечать дела в списке дел гости могут при guestsCanCheck = true, заметка недоступна по ссылке',
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
                                    property: 'isShared',
                                    description: 'Открывается ли список по общей ссылке: у списка желаний и списка дел — если доступ включён, у заметки всегда false',
                                    type: 'boolean',
                                    example: true
                                ),

                                new OA\Property(
                                    property: 'guestsCanCheck',
                                    description: 'Разрешено ли гостям отмечать дела по ссылке; у списка желаний и заметки false. Действует, только если isShared = true',
                                    type: 'boolean',
                                    example: false
                                ),

                                new OA\Property(
                                    property: 'guestNameRequired',
                                    description: 'Должен ли гость указать имя, отмечая дела; у списка желаний и заметки false',
                                    type: 'boolean',
                                    example: true
                                ),

                                new OA\Property(
                                    property: 'dueDate',
                                    description: 'Срок списка дел или дата события списка желаний (Y-m-d); null — дата не указана, у заметки всегда null',
                                    type: 'string',
                                    format: 'date',
                                    nullable: true,
                                    example: '2026-12-31'
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
                                                property: 'urls',
                                                description: 'Ссылки на товар (http или https), не больше трёх; пустой массив — ссылок нет',
                                                type: 'array',
                                                maxItems: 3,
                                                items: new OA\Items(type: 'string', format: 'uri', maxLength: 2048),
                                                example: ['https://example.com/books/master-i-margarita']
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

                                            new OA\Property(
                                                property: 'checkedBy',
                                                description: 'Только у позиций списка дел: кто отметил дело выполненным',
                                                ref: '#/components/schemas/TodoItemCheckedBy',
                                                nullable: true
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
                    description: 'Тип списка: gift — список желаний, todo — список дел, note — заметка. Необязателен, по умолчанию gift. Задаётся только при создании. У позиций списка дел нельзя указать urls, priority и price. У заметки нет title и items, вместо них передаётся content. hideSelections может быть true только у списка желаний',
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
                    property: 'isShared',
                    description: 'Доступ к списку по общей ссылке. Необязателен, по умолчанию true для списка желаний и false для списка дел; для заметки (type = note) не передаётся',
                    type: 'boolean',
                    example: false
                ),

                new OA\Property(
                    property: 'guestNameRequired',
                    description: 'Должен ли гость указать имя, отмечая дела по ссылке. Необязателен, по умолчанию true; передаётся только для списка дел',
                    type: 'boolean',
                    example: true
                ),

                new OA\Property(
                    property: 'dueDate',
                    description: 'Срок списка дел или дата события списка желаний (Y-m-d). Необязательна; у заметки запрещена',
                    type: 'string',
                    format: 'date',
                    nullable: true,
                    example: '2026-12-31'
                ),

                new OA\Property(
                    property: 'guestsCanCheck',
                    description: 'Разрешить гостям отмечать дела выполненными по ссылке (снять отметку может только владелец). Необязателен, по умолчанию false; передаётся только для списка дел и действует при isShared = true',
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
                                property: 'urls',
                                description: 'Ссылки на товар (http или https), не больше трёх; пустой массив — ссылок нет',
                                type: 'array',
                                maxItems: 3,
                                items: new OA\Items(type: 'string', format: 'uri', maxLength: 2048),
                                example: ['https://example.com/books/master-i-margarita']
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
                                description: 'Тип списка: gift — список желаний, todo — список дел, note — заметка. Список желаний и список дел открываются по общей ссылке только при isShared = true, отмечать дела в списке дел гости могут при guestsCanCheck = true, заметка недоступна по ссылке',
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
                                property: 'isShared',
                                description: 'Открывается ли список по общей ссылке: у списка желаний и списка дел — если доступ включён, у заметки всегда false',
                                type: 'boolean',
                                example: true
                            ),

                            new OA\Property(
                                property: 'guestsCanCheck',
                                description: 'Разрешено ли гостям отмечать дела по ссылке; у списка желаний и заметки false. Действует, только если isShared = true',
                                type: 'boolean',
                                example: false
                            ),

                            new OA\Property(
                                property: 'guestNameRequired',
                                description: 'Должен ли гость указать имя, отмечая дела; у списка желаний и заметки false',
                                type: 'boolean',
                                example: true
                            ),

                            new OA\Property(
                                property: 'dueDate',
                                description: 'Срок списка дел или дата события списка желаний (Y-m-d); null — дата не указана',
                                type: 'string',
                                format: 'date',
                                nullable: true,
                                example: '2026-12-31'
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
                                            property: 'urls',
                                            description: 'Ссылки на товар (http или https), не больше трёх; пустой массив — ссылок нет',
                                            type: 'array',
                                            maxItems: 3,
                                            items: new OA\Items(type: 'string', format: 'uri', maxLength: 2048),
                                            example: ['https://example.com/books/master-i-margarita']
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

                                        new OA\Property(
                                            property: 'checkedBy',
                                            description: 'Только у позиций списка дел: кто отметил дело выполненным',
                                            ref: '#/components/schemas/TodoItemCheckedBy',
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
    summary: 'Получить выбор гостей и время, на которое он получен',
    description: 'Окно редактирования запрашивает выбор при открытии и когда владелец выключает режим сюрприза. checkedAt передаётся при снятии выбора. В режиме сюрприза itemIds есть только при reveal=1, иначе ответ выбор не раскрывает',
    tags: ['Wishlists'],
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(name: 'id', description: 'ID списка', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        new OA\Parameter(name: 'reveal', description: 'Вернуть itemIds и в режиме сюрприза: владелец выключил режим в окне', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
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
                                property: 'checkedAt',
                                description: 'Серверное время, на которое получен выбор',
                                type: 'string',
                                format: 'date-time',
                                example: '2026-10-07T09:15:00+00:00'
                            ),

                            new OA\Property(
                                property: 'itemIds',
                                description: 'id выбранных позиций; нет в режиме сюрприза без reveal=1',
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

#[OA\Delete(
    path: '/v1/wishlists/{id}/items/{itemId}/selection',
    summary: 'Снять выбор гостя с позиции списка желаний',
    description: 'Владелец не ставит отметки в списке желаний сам и не меняет их при изменении списка (isSelected в items игнорируется), а только снимает выбор гостя этим запросом. Бронь позиции и совместный подарок на неё удаляются. Выбор, сделанный не раньше checkedAt, не снимается (409): владелец его не видел. Ответ одинаков, была позиция выбрана или нет, поэтому в режиме сюрприза владелец освобождает позицию, не узнавая этого. Для списка дел возвращается 422',
    tags: ['Wishlists'],
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(name: 'id', description: 'ID списка', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        new OA\Parameter(name: 'itemId', description: 'ID позиции', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
    ],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['checkedAt'],
            properties: [
                new OA\Property(
                    property: 'checkedAt',
                    description: 'Время, на которое окно получило выбор гостей (checkedAt из GET /v1/wishlists/{id}/selections)',
                    type: 'string',
                    format: 'date-time',
                    example: '2026-10-07T09:15:00+00:00'
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
            response: 409,
            description: 'Позицию выбрали после checkedAt, выбор не снят'
        ),

        new OA\Response(
            response: 422,
            description: 'Ошибка валидации или список не является списком желаний'
        ),

        new OA\Response(
            response: 401,
            description: 'Не авторизован'
        ),
    ]
)]

class WishlistController extends Controller {}
