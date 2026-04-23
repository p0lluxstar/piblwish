## Измененные файлы

### 1. `composer.json`

Добавлена зависимость:

```json
"laravel/sanctum": "^4.3"
```

Зачем:

- подключает пакет Sanctum;
- дает возможность создавать personal access tokens;
- позволяет использовать middleware `auth:sanctum`.

### 2. `composer.lock`

Файл обновился автоматически после установки зависимостей Composer.

Зачем:

- фиксирует точные версии пакетов;
- нужен для воспроизводимой установки проекта на другой машине.

### 3. `bootstrap/app.php`

Добавено подключение API-роутов:

```php
api: __DIR__.'/../routes/api.php',
```

Зачем:

- Laravel начинает загружать файл `routes/api.php`;
- все маршруты из этого файла получают префикс `/api`.

### 4. `routes/api.php`

Создан новый файл API-роутов.

Сейчас в нем зарегистрированы маршруты:

- `POST /api/register`
- `POST /api/login`
- `POST /api/logout`
- `GET /api/user`

Зачем:

- `register` создает пользователя и сразу выдает токен;
- `login` проверяет email и пароль, затем выдает токен;
- `logout` удаляет текущий токен;
- `user` возвращает текущего авторизованного пользователя.

Защищенные роуты работают через middleware:

```php
auth:sanctum
```

Это значит, что для `logout` и `user` нужно передавать Bearer token.

### 5. `app/Http/Controllers/Api/AuthController.php`

Создан контроллер API-аутентификации.

Сейчас в нем есть три метода:

#### `register(Request $request)`

Что делает:

- валидирует `name`, `email`, `password`;
- создает пользователя в таблице `users`;
- генерирует Sanctum token;
- возвращает JSON-ответ с пользователем и токеном.

#### `login(Request $request)`

Что делает:

- валидирует `email` и `password`;
- ищет пользователя по email;
- проверяет пароль через `Hash::check(...)`;
- если данные неверные, выбрасывает `ValidationException`;
- если данные верные, создает токен и возвращает его в JSON.

#### `logout(Request $request)`

Что делает:

- получает текущего авторизованного пользователя;
- удаляет текущий access token;
- возвращает JSON с сообщением об успешном выходе.

### 6. `app/Models/User.php`

В модель пользователя добавлен trait:

```php
use Laravel\Sanctum\HasApiTokens;
```

И подключен в классе:

```php
use HasFactory, Notifiable, HasApiTokens;
```

Зачем:

- разрешает вызывать `$user->createToken(...)`;
- связывает модель `User` с токенами Sanctum.

### 7. `config/sanctum.php`

Добавлен конфигурационный файл Sanctum.

Зачем:

- хранит настройки stateful-доменов;
- задает guard-ы, через которые Sanctum пробует аутентификацию;
- содержит конфигурацию middleware и срока жизни токенов.

### 8. `database/migrations/2026_04_23_204119_create_personal_access_tokens_table.php`

Добавлена миграция таблицы `personal_access_tokens`.

Зачем:

- в этой таблице Sanctum хранит токены;
- без нее выдача и проверка токенов работать не будут.

## Что теперь умеет приложение

После этих изменений приложение поддерживает token-based аутентификацию через Sanctum.

Типовой сценарий теперь такой:

1. Клиент отправляет `POST /api/register` или `POST /api/login`.
2. Сервер возвращает токен.
3. Клиент сохраняет токен.
4. При обращении к защищенным маршрутам клиент передает заголовок:

```http
Authorization: Bearer <token>
```

5. `auth:sanctum` проверяет токен и определяет пользователя.

## Текущие API-маршруты

### `POST /api/register`

Пример body:

```json
{
  "name": "Test User",
  "email": "test@example.com",
  "password": "password"
}
```

### `POST /api/login`

Пример body:

```json
{
  "email": "test@example.com",
  "password": "password"
}
```

### `GET /api/user`

Требует Bearer token.

Пример заголовка:

```http
Authorization: Bearer <token>
Accept: application/json
```

### `POST /api/logout`

Требует Bearer token.

Назначение:

- удаляет именно текущий токен, с которым пришел запрос.

## Что нужно сделать после установки Sanctum

Если это еще не выполнено, нужно:

```bash
php artisan migrate
```

Это создаст таблицу `personal_access_tokens`.

## Как дебажить логин

Для временной отладки в `AuthController` можно использовать:

```php
Log::info('Login attempt', [
    'email' => $request->input('email'),
    'ip' => $request->ip(),
]);
```

Лучше не логировать:

- пароль;
- токен;
- заголовок `Authorization`.

Логи смотреть здесь:

- `storage/logs/laravel.log`
- или командой `php artisan pail`

## Замечания

- Сейчас в ответах контроллера видны строки с битой кодировкой (`Р...`). Это похоже на проблему кодировки файла, а не логики Sanctum.
- Текущая реализация использует именно Bearer token flow, а не cookie-based SPA auth. На cookie-based SPA auth надо будет исправить, когда начнеться реалзиация фронтенда.
