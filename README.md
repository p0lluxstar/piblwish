# Laravel + Vue + Vite + MySQL

Минимальный стартовый проект на Laravel с Vue (SPA) и Vite.

---

## 📦 Стек

* PHP / Laravel
* MySQL
* Vue 3
* Vite
* Node.js / npm
* Prettier
* ESLint

---

## 🚀 Установка проекта

### 1. Создание проекта

```bash
composer create-project laravel/laravel .
```

---

### 2. Настройка базы данных

Создай базу данных, например:

```
my_project
```

В `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=my_project
DB_USERNAME=root
DB_PASSWORD=
```

Очистить кеш:

```bash
php artisan config:clear
```

Запустить миграции:

```bash
php artisan migrate
```

---

### 3. Установка frontend

```bash
npm install
npm install vue @vitejs/plugin-vue
```

---

### 4. Запуск проекта

В двух терминалах:

```bash
php artisan serve
```

```bash
npm run dev
```

Открыть в браузере:

```
http://127.0.0.1:8000
```

---

## 🐳 Запуск в Docker локально

Нужен запущенный Docker Desktop. Используются `docker-compose.yml` и `_docker/app/Dockerfile`
(файлы `docker-compose.prod.yml` и `_docker/app/Dockerfile.prod` — для прод-сервера).

Контейнеры: `nginx` (порт 8876), `app` (PHP-FPM), `queue` (очередь, через неё уходят письма),
`scheduler` (планировщик, напоминания о сроке) и `db` (MySQL, порт 3307).

### `.env`

Подходит тот же `.env`, что и для OSPanel: адрес базы (`DB_HOST=db`), пользователь `root`,
пароль и `APP_URL=http://localhost:8876` задаются в `docker-compose.yml` и имеют приоритет
над `.env`. Из `.env` берутся остальные настройки, в том числе `DB_DATABASE` и почта.

Пароль root для MySQL в Docker по умолчанию `secret`. Другой пароль задаётся в `.env`:

```env
DOCKER_DB_PASSWORD=другой_пароль
```

### Первый запуск

```bash
composer install
npm install
docker compose up -d --build
docker compose exec app php artisan migrate
```

Код подключён в контейнеры томом, поэтому `vendor` и `node_modules` берутся из каталога
проекта. Если PHP и Composer на компьютере не установлены, зависимости можно поставить
в контейнере: `docker compose exec app composer install`.

### Ежедневный запуск

```bash
docker compose up -d
npm run dev:docker
```

Открыть в браузере:

```
http://localhost:8876
```

`npm run dev:docker` запускает Vite с адресом API `http://localhost:8876` из `.env.docker`.
Для OSPanel по-прежнему используется `npm run dev`.

### Полезные команды

```bash
docker compose ps                              # состояние контейнеров
docker compose logs -f app queue               # логи
docker compose exec app php artisan <команда>  # artisan внутри контейнера
docker compose down                            # остановить контейнеры (данные базы сохраняются)
```

### Особенности

* База данных отдельная от OSPanel и хранится в `_docker/db/data`.
* MySQL задаёт пароль root только при первом создании базы. Если `_docker/db/data` создан
  с другим паролем, Laravel не подключится (`Access denied`): укажите прежний пароль
  в `DOCKER_DB_PASSWORD` или, если локальные данные не нужны, выполните `docker compose down`,
  очистите `_docker/db/data` и запустите контейнеры заново.
* Фото пользователей nginx отдаёт напрямую из `storage/app/public`, `php artisan storage:link`
  для Docker не нужен.

---

## 🧩 Структура

```
resources/
 ├── js/
 │    ├── app.js
 │    └── App.vue
 └── views/
      └── welcome.blade.php
```