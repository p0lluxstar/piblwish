<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\SavedWishlistController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WishlistController;
use App\Http\Controllers\EmailReminderController;
use App\Http\Controllers\SharedWishlistPageController;
use App\Http\Middleware\RenewRememberCookie;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

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
    Route::post('/verify-registration/resend', [AuthController::class, 'resendVerificationCode'])
        ->middleware('throttle:verify-code-resend');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])
        ->middleware('throttle:password-reset-request');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])
        ->middleware('throttle:password-reset');
});

// Только для авторизованных пользователей (через Sanctum)
Route::prefix('v1')->middleware(['auth:sanctum', 'throttle:dashboard', RenewRememberCookie::class])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/logout-all', [AuthController::class, 'logoutAllDevices']);
    Route::get('/user', [UserController::class, 'user']);
    Route::patch('/user', [UserController::class, 'updateUser']);
    Route::post('/user/settings/reset', [UserController::class, 'resetSettings']);
    Route::delete('/user', [UserController::class, 'deleteAccount']);
    Route::post('/user/avatar', [UserController::class, 'updateAvatar'])
        ->middleware('throttle:avatar-upload');
    Route::delete('/user/avatar', [UserController::class, 'deleteAvatar']);
    Route::put('/user/password', [UserController::class, 'changePassword'])
        ->middleware('throttle:password-change');
    Route::post('/user/email', [UserController::class, 'requestEmailChange'])
        ->middleware('throttle:email-change-request');
    Route::post('/user/email/confirm', [UserController::class, 'confirmEmailChange'])
        ->middleware('throttle:email-change-confirm');
    Route::get('/wishlists', [WishlistController::class, 'getUserWishlists']);
    Route::post('/wishlists', [WishlistController::class, 'createWishlist']);
    Route::patch('/wishlists/{id}', [WishlistController::class, 'updateWishlist']);
    Route::get('/wishlists/{id}/selections', [WishlistController::class, 'getSelectedItems']);
    Route::patch('/wishlists/{id}/items/{itemId}', [WishlistController::class, 'updateWishlistItem']);
    Route::delete('/wishlists/{id}/items/{itemId}/selection', [WishlistController::class, 'clearItemSelection']);
    // Выше /wishlists/{id}, иначе «archived» попадёт в {id}
    Route::delete('/wishlists/archived', [WishlistController::class, 'deleteArchivedWishlists']);
    Route::delete('/wishlists/{id}', [WishlistController::class, 'deleteWishlist']);
    Route::post('/wishlists/{id}/archive', [WishlistController::class, 'archiveWishlist']);
    Route::delete('/wishlists/{id}/archive', [WishlistController::class, 'restoreWishlist']);
    Route::post('/wishlists/{id}/share-token', [WishlistController::class, 'regenerateShareToken']);
    // Чужие списки, добавленные к себе с общей страницы; {id} — токен общей ссылки списка
    Route::get('/saved-wishlists', [SavedWishlistController::class, 'getSavedWishlists']);
    Route::post('/saved-wishlists/{id}', [SavedWishlistController::class, 'saveWishlist']);
    Route::delete('/saved-wishlists/{id}', [SavedWishlistController::class, 'removeSavedWishlist']);
});

// Отписка от напоминаний о сроке по ссылке из письма (EmailReminderController).
// Подпись проверяется без хоста и схемы (signed:relative), см. DueDateReminderService::unsubscribeUrl
Route::middleware('signed:relative')->group(function () {
    Route::get('/email-reminders/unsubscribe/{user}', [EmailReminderController::class, 'showUnsubscribe'])
        ->name('email-reminders.unsubscribe');
    Route::post('/email-reminders/unsubscribe/{user}', [EmailReminderController::class, 'unsubscribe'])
        ->withoutMiddleware(ValidateCsrfToken::class);
});

// Общая страница списка: SPA с мета-тегами списка для превью ссылки в мессенджерах.
// Прежние адреса (/shared, /shared-wishlists, /shared-todolists) SPA перенаправляет
// сама, но боты не выполняют JavaScript, поэтому теги нужны и на них
Route::get('/{prefix}/{id}', SharedWishlistPageController::class)
    ->where('prefix', 'shared|shared-wishlists?|shared-todolists?|shared-fund');

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
