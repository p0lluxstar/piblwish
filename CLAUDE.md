# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Проект

Сервис вишлистов: Laravel 13 (PHP 8.3+) как API-бэкенд и Vue 3 SPA (TypeScript, Vite), которое отдаётся из того же приложения. БД — MySQL. Комментарии в коде, сообщения коммитов и документация ведутся на русском языке.

## Команды

```bash
composer dev            # server + queue:listen + pail (логи) + vite одновременно
php artisan serve       # только бэкенд
npm run dev             # только Vite
npm run build           # сборка фронтенда

npm run lint            # ESLint (.js/.ts/.vue)
npm run type-check      # vue-tsc --noEmit
npm run format          # Prettier

composer test                                  # config:clear + php artisan test
php artisan test --filter=ИмяТеста             # один тест
php artisan test tests/Feature/ExampleTest.php # один файл
vendor/bin/pint                                # форматирование PHP

php artisan migrate
php artisan l5-swagger:generate                # пересобрать storage/api-docs/api-docs.json
```

Docker (`docker-compose.yml`): nginx на порту 8876, MySQL на 3307. Локально проект также запускается через OSPanel.

## Архитектура бэкенда

**Маршрутизация и аутентификация.** Используется cookie-based SPA-аутентификация Sanctum (`statefulApi()` в `bootstrap/app.php`), а не Bearer-токены. Поэтому вход, регистрация и все защищённые эндпоинты (`/v1/login`, `/v1/user`, `/v1/wishlists`…) находятся в `routes/web.php` и не имеют префикса `/api`. В `routes/api.php` (префикс `/api`) лежат только публичные эндпоинты расшаренных вишлистов. В конце `routes/web.php` стоит SPA-fallback `/{any}` → `welcome`; новые веб-маршруты добавляются **выше** него. Файл `docs/sanctum-auth-changes.md` описывает раннюю версию с Bearer-токенами и устарел.

**Слои.** Контроллер (`app/Http/Controllers/Api`) → FormRequest (`app/Http/Requests/<Домен>`) → сервис (`app/Services/<Домен>/<Домен>Service.php`, внедряется через конструктор) → Resource. Бизнес-логика находится в сервисах, контроллеры остаются тонкими.

**Формат ответов.** Успешные ответы оборачиваются в `{ success, statusCode, data }`: ресурсы наследуются от `App\Http\Resources\ApiResource` / `ApiCollection`. Ошибки для JSON-запросов приводятся к виду `{ success: false, statusCode, data: { message, errors } }` в обработчике исключений в `bootstrap/app.php`. Поэтому фронтенд читает данные из `response.data.data`, а текст ошибки — из `error.response.data.data.message`.

**Swagger.** OpenAPI-описания записаны атрибутами в отдельных классах `app/Http/Controllers/Swagger/*Controller.php`, а не в рабочих контроллерах. При изменении API нужно обновить соответствующий Swagger-класс.

**Модели.**

- `User`: первичный ключ — ULID; вход по `username`/`email`. Аккаунт активируется подтверждением email-кода (`VerificationRegistrationCode`, письмо `VerificationCodeMail`). Пароль восстанавливается кодом из письма (`PasswordResetCodeMail`); хеш кода хранится в стандартной таблице `password_reset_tokens`, новый код заменяет предыдущий.
- `Wishlist` → `WishlistItem`. Цвет списка `color` — ключ из enum `WishlistColor`; сами оттенки задаются во фронтенде (`constants/wishlistColors.ts`, `scss/ui/wishlistColors.scss`), а список ключей продублирован в типе `WishlistColor` в `types/wishlist.ts`, поэтому новый цвет добавляется во все эти места.
- `WishlistItem`: в БД название позиции хранится в колонке `description`, а в API и на фронтенде называется `label`. Порядок позиций хранится в `position` и задаётся порядком массива `items` в запросе. Необязательные поля: `url` (только http(s), до 2048 символов; фронтенд дописывает `https://` к адресу без схемы), `priority` (enum `WishlistItemPriority`, 1–3) и `price` (целые рубли 0–10 000 000). `null` в них означает «не указано». Приоритет и цена видны и в режиме сюрприза.
- Гости по ссылке отмечают позиции через `is_selected` (`SharedWishlistService` обновляет их в транзакции и отклоняет уже выбранные позиции). Флаг `hide_selections` (режим сюрприза) убирает `isSelected` из ответов владельцу; при редактировании списка в этом режиме выбор гостей переносится по `id` позиций, а `isSelected` из запроса игнорируется. Позиции при редактировании изменяются на месте по `id` (`WishlistService::syncItems`), а не пересоздаются.
- `WishlistReservation`: каждое сохранение выбора гостем создаёт бронь, выбранные позиции ссылаются на неё через `reservation_id`. В БД хранится только SHA-256 токена; по токену из `localStorage` или из ссылки `?reservation=` гость отменяет выбор отдельных позиций.

**Миграции.** Каталог `database/migrations/_baseline/` Laravel не загружает; это архивная копия исходной схемы.

## Архитектура фронтенда

- Точка входа — `resources/js/app.ts` (Vue Router + Pinia + TanStack Vue Query); алиас `@` → `resources/js`.
- HTTP-запросы идут только через `api` из `@/lib/api` (axios с `withCredentials`, `baseURL` из `VITE_API_URL`).
- Серверное состояние обрабатывается композаблами на Vue Query (`composables/useAuth.ts`); глобальное состояние пользователя хранится в Pinia-сторе `stores/auth.ts`.
- Доступ к маршрутам задаётся через `meta.requiresAuth` / `meta.requiresGuest`; guard в `router/index.ts` один раз вызывает `auth.fetchUser()`. `/shared-wishlists/:id` — публичная страница.
- Структура: `pages/` (страницы маршрутов), `layouts/` (`LandingLayout`, `DashboardLayout`), `components/<домен>/`, общие элементы — в `components/ui/`. Стили — SCSS (`resources/scss`) и Tailwind 4.

## Стиль кода

- ESLint требует явных типов возвращаемых значений функций (`explicit-function-return-type`), запрещает `any` и сортирует импорты (`simple-import-sort`).
- Prettier: 4 пробела, одинарные кавычки, точки с запятой.
