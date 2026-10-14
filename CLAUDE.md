# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Проект

Сервис вишлистов: Laravel 13 (PHP 8.3+) как API-бэкенд и Vue 3 SPA (TypeScript, Vite), которое отдаётся из того же приложения. БД — MySQL. Комментарии в коде, сообщения коммитов и документация ведутся на русском языке.

Подробные описания доменов лежат в `docs/architecture/`; перед работой над доменом прочитай нужный файл:

- `users.md` — вход, сессии и «Запомнить меня», регистрация, пароль, смена email, статистика, фон, фото, настройки и их сброс;
- `wishlists.md` — типы списков (желания, дела, заметка, сбор), позиции, архив, дубликаты, дата, цвет;
- `shared-access.md` — доступ по ссылке, токен ссылки, общая страница, выбор и режим сюрприза, брони, совместный подарок, отметки дел гостями, «Чужие списки»;
- `reminders.md` — напоминания о сроке на почту и отписка;
- `frontend.md` — маршруты SPA, архив, панель поиска и сортировки, сетка карточек, тема;
- `infrastructure.md` — Docker локально и на проде, CI, автодеплой, серверные скрипты.

## Команды

```bash
composer dev            # server + queue:listen + pail (логи) + vite одновременно
npm run build           # сборка фронтенда
npm run lint            # ESLint (.js/.ts/.vue)
npm run type-check      # vue-tsc --noEmit
npm run format          # Prettier

composer test                                  # config:clear + php artisan test
php artisan test --filter=ИмяТеста             # один тест
vendor/bin/pint                                # форматирование PHP
php artisan l5-swagger:generate                # пересобрать storage/api-docs/api-docs.json
```

Локально проект запускается через OSPanel или Docker. Прод работает из `main`: изменения попадают туда пулреквестом из `dev`, после push в `main` CI сам выкладывает код на сервер.

## Архитектура бэкенда

- **Аутентификация** — cookie-based SPA Sanctum (`statefulApi()`), а не Bearer-токены. Поэтому вход и все защищённые эндпоинты (`/v1/login`, `/v1/user`, `/v1/wishlists`…) находятся в `routes/web.php` без префикса `/api`. В `routes/api.php` лежат только публичные эндпоинты общих списков (`/api/v1/shared-wishlists`). `docs/sanctum-auth-changes.md` устарел.
- **Слои:** контроллер (`app/Http/Controllers/Api`, тонкий) → FormRequest (`app/Http/Requests/<Домен>`) → сервис (`app/Services/<Домен>/<Домен>Service.php`, бизнес-логика) → Resource.
- **Формат ответов:** успех — `{ success, statusCode, data }` (ресурсы наследуют `ApiResource` / `ApiCollection`, `additional()` выводится рядом с `data`, например `meta.archivedCount`); ошибка — `{ success: false, statusCode, data: { message, errors } }` (обработчик в `bootstrap/app.php`). Фронтенд читает `response.data.data` и `error.response.data.data.message`.
- **Swagger:** описания — атрибуты в `app/Http/Controllers/Swagger/*Controller.php`, а не в рабочих контроллерах.
- **Модели:** `User` (ULID) → `Wishlist` (`type`: `gift`, `todo`, `note`, `fund`) → `WishlistItem`; гостевые `WishlistReservation` и `WishlistJointGift`; закладки `SavedWishlist`.
- **Миграции:** каталог `database/migrations/_baseline/` Laravel не загружает, это архивная копия исходной схемы.

### Неочевидные соглашения

- Название позиции в БД — колонка `description`, в API и на фронтенде — `label`.
- У списка дел `is_selected` означает «выполнено», а `priority` — важность (молнии вместо сердец).
- Гостю не отдаётся настоящий `id` списка: общие ссылки, публичные эндпоинты и закладки работают по `share_token` (scope `byShareToken`), и `id` в ответах общей страницы и «Чужих списков» — это токен.
- Открыт ли список по ссылке, проверяют только `Wishlist::isOpenByLink()` / scope `openByLink()` (доступ включён и не архив). Действия гостей (выбор, брони, совместный подарок) доступны только у списка желаний (`SharedWishlistService::sharedQuery()`); список дел и сбор гость только смотрит, заметка по ссылке не открывается никогда.
- Значения по умолчанию, зависящие от типа (`is_shared`, `share_token`), задаёт `Wishlist::booted()`; режим сюрприза у нового списка желаний включает `WishlistService::createWishlist`.
- Поля, недопустимые для типа, `CreateWishlistRequest` отклоняет, а `WishlistService` очищает или игнорирует при изменении.
- Владелец не видит выбор гостей в режиме сюрприза и никогда не видит совместные подарки.
- Архивный список владелец только просматривает и удаляет (иначе 409, `WishlistService::ensureNotArchived`).
- Позиции при редактировании изменяются на месте по `id` (`WishlistService::syncItems`), а не пересоздаются: к ним привязаны выбор гостей и брони.

## Правила синхронизации

Когда меняешь одно из этого, обнови и остальное:

- **Новый веб-маршрут** — выше SPA-fallback `/{any}` в `routes/web.php`; новый маршрут Vue — выше `/:pathMatch(.*)*` в роутере.
- **Изменение API** — соответствующий Swagger-класс.
- **Новый цвет списка** — enum `WishlistColor`, тип `WishlistColor` в `types/wishlist.ts`, `constants/wishlistColors.ts`, `scss/ui/wishlistColors.scss` (обе карты, светлая и `$wishlist-colors-dark`).
- **Новый фон** — enum `AppBackground`, тип `AppBackground` в `types/user.ts`, `constants/appBackgrounds.ts`, `scss/ui/appBackgrounds.scss` (обе карты) и `scss/ui/appBackgroundGradients.scss` (обе карты). Светлый фон, на котором текст должен быть тёмным, задаёт ещё `ink`, `ink-soft`, `accent`.
- **Новая настройка пользователя или новое значение по умолчанию у `users`** — `UserService::resetSettings` (проверяет `UserSettingsResetTest`).
- **Платформы сборов** — `config/wishlist.php` (`fund_hosts`) и `constants/fundHosts.ts`.
- **Лимиты позиций и списков** — `config/wishlist.php` (`max_items`, `max_wishlists`) и `constants/wishlistLimits.ts`; из этой константы их берут форма списка и справка.
- **Срок напоминаний по умолчанию** — `DueDateReminderService::DEFAULT_DAYS` и `ReminderDaysPicker`.
- **Удаление аккаунта** — закладки и каталог фото удаляются явно в `UserService::deleteAccount`, так как запись пользователя остаётся.

## Архитектура фронтенда

- Точка входа — `resources/js/app.ts` (Vue Router + Pinia + TanStack Vue Query); алиас `@` → `resources/js`.
- HTTP-запросы идут только через `api` из `@/lib/api` (axios с `withCredentials`, `baseURL` из `VITE_API_URL`); `lib/authInterceptor.ts` обрабатывает 419 и 401.
- Серверное состояние — композаблы на Vue Query (`composables/`), пользователь — Pinia-стор `stores/auth.ts`.
- Доступ к маршрутам — `meta.requiresAuth` / `meta.requiresGuest`; guard в `router/index.ts` один раз вызывает `auth.fetchUser()`.
- Структура: `pages/`, `layouts/` (`LandingLayout`, `DashboardLayout`), `components/<домен>/`, общие элементы — `components/ui/`. Стили — SCSS (`resources/scss`) и Tailwind 4.
- Цвета текста и поверхностей берутся только из переменных `css/app.css` (`--ink`, `--surface`… с тёмными значениями под `:root[data-theme='dark']`); белый `#fff` — только на градиентных кнопках. Текст прямо на фоне приложения — `var(--app-ink, var(--ink))`.

## Стиль кода

- ESLint требует явных типов возвращаемых значений функций (`explicit-function-return-type`), запрещает `any` и сортирует импорты (`simple-import-sort`).
- Prettier: 4 пробела, одинарные кавычки, точки с запятой.
