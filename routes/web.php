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

// Аутентификация (stateful, через сессии)
Route::prefix('v1')->middleware('throttle:5,1')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/verify-registration', [AuthController::class, 'verifyRegistrationCode']);
});

Route::prefix('v1')->middleware(['auth:sanctum', 'throttle:30,1'])->group(function () {
    Route::get('/user', [UserController::class, 'user']);
    Route::get('/wishlists', [WishlistController::class, 'userWishlists']);
    Route::post('/wishlists', [WishlistController::class, 'createWishlist']);
});

// Logout (требует авторизации)
Route::post('/v1/logout', [AuthController::class, 'logout'])
    ->middleware(['auth:sanctum', 'throttle:30,1']);


// SPA fallback (ВСЕГДА в самом конце!)
Route::get('/{any}', function () {
    return view('welcome');
})->where('any', '.*');
