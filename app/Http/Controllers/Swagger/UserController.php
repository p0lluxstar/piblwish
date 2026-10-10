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
                            new OA\Property(
                                property: 'showFriendsEvents',
                                description: 'Показывать в дашборде строку «Скоро у друзей» с ближайшими датами чужих списков',
                                type: 'boolean',
                                example: true
                            ),
                            new OA\Property(
                                property: 'avatarUrl',
                                description: 'Адрес фотографии пользователя; null — фотографии нет, показывается первая буква имени',
                                type: 'string',
                                nullable: true,
                                example: 'https://example.com/storage/avatars/01jq3v7x8k2m4n6p8r0t2w4y6z/01jq4a2b3c4d5e6f7g8h9j0k1m.webp'
                            ),
                            new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2026-09-12T10:15:00+00:00'),
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
    description: 'Сохраняет фон приложения и показ строки «Скоро у друзей» в дашборде. Можно передать одну или обе настройки, непереданная не меняется; запрос без них отклоняется (422). Фон по умолчанию — blossom, строка по умолчанию показывается. Возвращает пользователя в том же виде, что GET /v1/user.',
    tags: ['User'],
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'showFriendsEvents',
                    description: 'Показывать в дашборде строку «Скоро у друзей»',
                    type: 'boolean',
                    example: false
                ),
                new OA\Property(
                    property: 'background',
                    description: 'Обязателен, если не передан showFriendsEvents',
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
                            new OA\Property(property: 'showFriendsEvents', type: 'boolean', example: true),
                            new OA\Property(property: 'avatarUrl', type: 'string', nullable: true, example: null),
                            new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2026-09-12T10:15:00+00:00'),
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
            description: 'Ошибка валидации: не передана ни одна настройка, такого фона нет или showFriendsEvents не логическое значение',
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

#[OA\Post(
    path: '/v1/user/avatar',
    summary: 'Загрузить фотографию пользователя',
    description: 'Принимает изображение JPEG, PNG, WebP или GIF (у анимации берётся первый кадр) до 2 МБ и до 4096×4096 пикселей. '
        .'Изображение перекодируется в WebP 256×256: обрезается по центру до квадрата, учитывается поворот по EXIF, метаданные удаляются. '
        .'Файл получает новое имя, прежняя фотография удаляется. Возвращает пользователя в том же виде, что GET /v1/user. '
        .'Лимит: 10 запросов в минуту и 30 в час.',
    tags: ['User'],
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                required: ['avatar'],
                properties: [
                    new OA\Property(property: 'avatar', type: 'string', format: 'binary'),
                ]
            )
        )
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Фотография сохранена',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'statusCode', type: 'integer', example: 200),
                    new OA\Property(
                        property: 'data',
                        properties: [
                            new OA\Property(property: 'id', type: 'string', example: '01jq3v7x8k2m4n6p8r0t2w4y6z'),
                            new OA\Property(property: 'username', type: 'string', example: 'john_doe'),
                            new OA\Property(property: 'email', type: 'string', example: 'john@example.com'),
                            new OA\Property(property: 'background', type: 'string', example: 'blossom'),
                            new OA\Property(property: 'showFriendsEvents', type: 'boolean', example: true),
                            new OA\Property(
                                property: 'avatarUrl',
                                type: 'string',
                                example: 'https://example.com/storage/avatars/01jq3v7x8k2m4n6p8r0t2w4y6z/01jq4a2b3c4d5e6f7g8h9j0k1m.webp'
                            ),
                            new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2026-09-12T10:15:00+00:00'),
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
            description: 'Файл не передан, не является изображением допустимого формата, больше 2 МБ или 4096×4096 пикселей либо не может быть декодирован',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: false),
                    new OA\Property(property: 'statusCode', type: 'integer', example: 422),
                    new OA\Property(
                        property: 'data',
                        properties: [
                            new OA\Property(property: 'message', type: 'string', example: 'Файл должен быть не больше 2 МБ'),
                            new OA\Property(
                                property: 'errors',
                                type: 'object',
                                example: ['avatar' => ['Файл должен быть не больше 2 МБ']]
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
    path: '/v1/user/avatar',
    summary: 'Удалить фотографию пользователя',
    description: 'Удаляет файл фотографии; вместо неё снова показывается первая буква имени. Если фотографии нет, запрос тоже выполняется успешно. Возвращает пользователя в том же виде, что GET /v1/user.',
    tags: ['User'],
    security: [['bearerAuth' => []]],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Фотография удалена',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'statusCode', type: 'integer', example: 200),
                    new OA\Property(
                        property: 'data',
                        properties: [
                            new OA\Property(property: 'id', type: 'string', example: '01jq3v7x8k2m4n6p8r0t2w4y6z'),
                            new OA\Property(property: 'username', type: 'string', example: 'john_doe'),
                            new OA\Property(property: 'email', type: 'string', example: 'john@example.com'),
                            new OA\Property(property: 'background', type: 'string', example: 'blossom'),
                            new OA\Property(property: 'showFriendsEvents', type: 'boolean', example: true),
                            new OA\Property(property: 'avatarUrl', type: 'string', nullable: true, example: null),
                            new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2026-09-12T10:15:00+00:00'),
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

#[OA\Post(
    path: '/v1/user/email',
    summary: 'Запросить смену email',
    description: 'Проверяет текущий пароль и отправляет 6-значный код на новый адрес. Код действует 15 минут; новый запрос заменяет предыдущий вместе с его кодом и счётчиком попыток. Email меняется только после подтверждения через POST /v1/user/email/confirm. Лимит: 3 запроса в минуту и 10 в час.',
    tags: ['User'],
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['email', 'current_password'],
            properties: [
                new OA\Property(
                    property: 'email',
                    description: 'Новый email: не занят и отличается от текущего без учёта регистра',
                    type: 'string',
                    example: 'new@example.com'
                ),
                new OA\Property(property: 'current_password', type: 'string', example: 'password123'),
            ]
        )
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Код отправлен на новый адрес',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'statusCode', type: 'integer', example: 200),
                    new OA\Property(
                        property: 'data',
                        properties: [
                            new OA\Property(property: 'message', type: 'string', example: 'Код отправлен на новый email'),
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
            description: 'Ошибка валидации: некорректный или занятый email, совпадает с текущим, неверный текущий пароль',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: false),
                    new OA\Property(property: 'statusCode', type: 'integer', example: 422),
                    new OA\Property(
                        property: 'data',
                        properties: [
                            new OA\Property(property: 'message', type: 'string', example: 'Такой email уже зарегистрирован'),
                            new OA\Property(
                                property: 'errors',
                                type: 'object',
                                example: ['email' => ['Такой email уже зарегистрирован']]
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

        new OA\Response(
            response: 503,
            description: 'Письмо не удалось поставить в очередь; запрос на смену не сохранён'
        ),
    ]
)]

#[OA\Post(
    path: '/v1/user/email/confirm',
    summary: 'Подтвердить новый email',
    description: 'Проверяет код из письма и меняет email. После 5 неверных попыток, по истечении срока кода или если адрес заняли после запроса, запрос на смену удаляется и код нужно запросить заново. Код восстановления пароля для прежнего адреса удаляется, на прежний адрес отправляется уведомление. Сессии не завершаются. Возвращает пользователя в том же виде, что GET /v1/user. Лимит: 5 запросов в минуту и 20 в час.',
    tags: ['User'],
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['code'],
            properties: [
                new OA\Property(property: 'code', description: '6 цифр', type: 'string', example: '123456'),
            ]
        )
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Email изменён',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'statusCode', type: 'integer', example: 200),
                    new OA\Property(
                        property: 'data',
                        properties: [
                            new OA\Property(property: 'id', type: 'string', example: '01jq3v7x8k2m4n6p8r0t2w4y6z'),
                            new OA\Property(property: 'username', type: 'string', example: 'john_doe'),
                            new OA\Property(property: 'email', type: 'string', example: 'new@example.com'),
                            new OA\Property(property: 'background', type: 'string', example: 'blossom'),
                            new OA\Property(property: 'showFriendsEvents', type: 'boolean', example: true),
                            new OA\Property(property: 'avatarUrl', type: 'string', nullable: true, example: null),
                            new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2026-09-12T10:15:00+00:00'),
                        ],
                        type: 'object'
                    ),
                ],
                type: 'object'
            )
        ),

        new OA\Response(
            response: 400,
            description: 'Неверный код, код истёк или превышено число попыток',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: false),
                    new OA\Property(property: 'statusCode', type: 'integer', example: 400),
                    new OA\Property(
                        property: 'data',
                        properties: [
                            new OA\Property(property: 'message', type: 'string', example: 'Неверный код'),
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
            response: 404,
            description: 'Запроса на смену email нет'
        ),

        new OA\Response(
            response: 409,
            description: 'Новый адрес заняли после запроса кода'
        ),

        new OA\Response(
            response: 422,
            description: 'Ошибка валидации: код не передан или не состоит из 6 цифр'
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
