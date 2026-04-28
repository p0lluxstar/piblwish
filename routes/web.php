<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Middleware\TransformApiResponse;
use Illuminate\Support\Facades\Route;

Route::get('/{any}', function () {
    return view('welcome');
})->where('any', '.*');