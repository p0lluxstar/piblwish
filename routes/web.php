<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WishlistController;

/*
|--------------------------------------------------------------------------
| Web Routes (STATEFUL)
|--------------------------------------------------------------------------
|
| Эти маршруты работают в stateful-режиме:
| - используют сессии (laravel_session)
| - используют cookie
| - защищены CSRF
| - подходят для SPA (Sanctum, login/logout)
|
| Здесь должны быть:
| - аутентификация (login, logout, register)
| - любые маршруты, где нужен session-based auth
|
*/

// Публичные маршруты (без авторизации).
// Лимитеры описаны в AppServiceProvider::configureRateLimiting()
Route::prefix('v1')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:login');
    Route::post('/register', [AuthController::class, 'register'])
        ->middleware('throttle:registration');
    Route::post('/verify-registration', [AuthController::class, 'verifyRegistrationCode'])
        ->middleware('throttle:verify-code');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])
        ->middleware('throttle:password-reset-request');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])
        ->middleware('throttle:password-reset');
});

// Только для авторизованных пользователей (через Sanctum)
Route::prefix('v1')->middleware(['auth:sanctum', 'throttle:dashboard'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [UserController::class, 'user']);
    Route::delete('/user', [UserController::class, 'deleteAccount']);
    Route::put('/user/password', [UserController::class, 'changePassword'])
        ->middleware('throttle:password-change');
    Route::get('/wishlists', [WishlistController::class, 'getUserWishlists']);
    Route::post('/wishlists', [WishlistController::class, 'createWishlist']);
    Route::patch('/wishlists/{id}', [WishlistController::class, 'updateWishlist']);
    Route::get('/wishlists/{id}/selections', [WishlistController::class, 'getSelectedItems']);
    Route::patch('/wishlists/{id}/items/{itemId}', [WishlistController::class, 'updateWishlistItem']);
    Route::delete('/wishlists/{id}', [WishlistController::class, 'deleteWishlist']);
});

/**
 * SPA Fallback
 * 
 * Все запросы, которые не попали под API или другие маршруты,
 * перенаправляются на главную страницу.
 * 
 * Это необходимо для работы клиентского роутинга (Vue Router / React Router):
 * - Пользователь может обновить страницу на любом URL
 * - Можно делиться ссылками на конкретные страницы
 * - SEO-боты получают HTML (если настроен SSR)
 * 
 * ВАЖНО: Этот маршрут должен быть ПОСЛЕДНИМ в файле routes/web.php!
 * Иначе он перехватит все запросы, включая API.
 *
 * Префиксы API (api/, v1/, sanctum/) исключены из шаблона: иначе запрос
 * к несуществующему эндпоинту получил бы HTML-страницу с кодом 200
 * вместо JSON-ответа с кодом 404.
 */
Route::get('/{any}', function () {
    return view('welcome');
})->where('any', '^(?!(api|v1|sanctum)(/|$)).*');
