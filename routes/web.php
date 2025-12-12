<?php

use App\Http\Controllers\PostController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;

Route::get('/', [PostController::class, 'index'])->name('home');

Route::get('about', function () {
    return view('pages.about.index');
})->name('about');

// Для авторизованных и верифицированынх
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [UserController::class, 'dashboard'])->name('dashboard');
});

// Для авторизованных
Route::middleware('auth')->group(function () {
    // Подтверждение регистрации
    // Обработчик проверки электронной почты
    Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
        $request->fulfill();

        return redirect()->route('dashboard');
    })->middleware('signed')->name('verification.verify');

    // Повторная отправка письма с подтверждением.
    Route::post('/email/verification-notification', function (Request $request) {
        $request->user()->sendEmailVerificationNotification();

        return back()->with('message', 'Verification link sent!');
    })->middleware('throttle:2,1')->name('verification.send');

    // Маршрут на страницу с профилем !!! Еще не сделано
    Route::get('profile', function () {})->middleware('verified');

    // Выход пользователя
    Route::post('logout', [UserController::class, 'logout'])->name('logout');
});

// Для гостей 
Route::middleware('guest')->group(function () {
    // Уведомление о подтверждении электронной почты 
    Route::get('verify-email', function () {
        return view('pages.user.verify-email');
    })->name('verification.notice');

    Route::get('login', function () {
        return view('pages.user.login');
    })->name('login');

    Route::post('login', [UserController::class, 'login'])->name('user.login');

    Route::get('register', function () {
        return view('pages.user.register');
    })->name('register');

    Route::post('register', [UserController::class, 'store'])->name('user.store');

    Route::get('forgot-password', function () {
        return view('pages.user.forgot-password');
    })->name('password.request');

    Route::post('forgot-password', [UserController::class, 'forgotPassword'])->name('password.email')->middleware('throttle:3,1');

    Route::get('reset-password/{token}', function (string $token) {
        return view('pages.user.reset-password', ['token' => $token]);
    })->middleware('guest')->name('password.reset');

    Route::post('reset-password', [UserController::class, 'resetPasswordUpdate'])->name('password.update');
});
