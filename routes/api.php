<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes (STATELESS)
|--------------------------------------------------------------------------
|
| Эти маршруты по умолчанию stateless:
| - НЕ используют сессии
| - НЕ используют cookie (если не настроен Sanctum stateful)
| - НЕ требуют CSRF
|
| Подходят для:
| - REST API
| - мобильных клиентов
| - внешних интеграций
|
| Аутентификация обычно:
| - через токены (Bearer / API tokens)
| - или через Sanctum (auth:sanctum)
|
| Важно:
| Если используешь Sanctum SPA (cookie-based),
| лучше держать login/logout в web.php
|
*/

// Только для авторизованных пользователей (через Sanctum)
Route::middleware(['auth:sanctum', 'throttle:30,1'])->prefix('v1')->group(function () {

    // Проверка текущего пользователя
    Route::get('/user', function (\Illuminate\Http\Request $request) {
        return response()->json([
            'data' => $request->user()
        ]);
    });

    // Пример защищённых маршрутов
    Route::get('/dashboard', function () {
        return response()->json([
            'data' => 'protected data'
        ]);
    });
});
