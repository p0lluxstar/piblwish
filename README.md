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

## 🧩 Структура

```
resources/
 ├── js/
 │    ├── app.js
 │    └── App.vue
 └── views/
      └── welcome.blade.php
```