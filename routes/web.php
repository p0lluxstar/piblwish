<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Middleware\TransformApiResponse;
use Illuminate\Support\Facades\Route;

Route::get('/{any}', function () {
    return view('welcome');
})->where('any', '.*');

Route::prefix('api/v1')->group(function () {

    // Приводит все JSON ответы к единому формату:
    Route::middleware([TransformApiResponse::class])->group(function () {

        // Только для неавторизованных пользователей, надо указать 'guest'
        Route::middleware(['throttle:5,1'])->group(function () {
            Route::post('/register', [AuthController::class, 'register']);
            Route::post('/login', [AuthController::class, 'login']);
        });


        Route::middleware(['auth', 'throttle:30,1'])->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
        });
    });
});
