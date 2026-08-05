<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WishlistController;
use App\Http\Controllers\Api\SharedWishlistController;

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

// Публичные маршруты (без авторизации)
Route::prefix('v1')->middleware('throttle:5,1')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/verify-registration', [AuthController::class, 'verifyRegistrationCode']);

    // Route::get('/shared-wishlists/{id}', [SharedWishlistController::class, 'getWishlistById']);
    // Route::patch(
    //     '/shared-wishlists/{wishlist}/items',
    //     [SharedWishlistController::class, 'updateSharedWishlistItems']
    // );
});

// Только для авторизованных пользователей (через Sanctum)
Route::prefix('v1')->middleware(['auth:sanctum', 'throttle:30,1'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [UserController::class, 'user']);
    Route::get('/wishlists', [WishlistController::class, 'getUserWishlists']);
    Route::post('/wishlists', [WishlistController::class, 'createWishlist']);
    Route::patch('/wishlists/{id}', [WishlistController::class, 'updateWishlist']);
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
 */
Route::get('/{any}', function () {
    return view('welcome');
})->where('any', '.*');
