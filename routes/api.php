<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\SharedWishlistController;

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

// Публичные API (без авторизации).
// Лимитеры описаны в AppServiceProvider::configureRateLimiting()
Route::prefix('v1')->group(function () {

    Route::get(
        '/shared-wishlists/{id}',
        [SharedWishlistController::class, 'getWishlistById']
    )->middleware('throttle:shared-read');

    Route::patch(
        '/shared-wishlists/{wishlist}/items',
        [SharedWishlistController::class, 'updateSharedWishlistItems']
    )->middleware('throttle:shared-write');
});

// Только для авторизованных пользователей (через Sanctum)
Route::middleware(['auth:sanctum', 'throttle:30,1'])->prefix('v1')->group(function () {

    // Проверка текущего пользователя
    // Route::get('/user', function (\Illuminate\Http\Request $request) {
    //     return response()->json([
    //         'data' => $request->user()
    //     ]);
    // });

    // Пример защищённых маршрутов
    // Route::get('/dashboard', function () {
    //     return response()->json([
    //         'data' => 'protected data'
    //     ]);
    // });
});
