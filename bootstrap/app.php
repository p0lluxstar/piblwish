<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\TransformApiResponse;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Включает stateful auth для Sanctum SPA, cookie auth + sessions + csrf
        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e) {
            return $request->expectsJson();
        });

        // Настраиваем формат ответа при ошибках
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->expectsJson()) {
                // Определяем статус-код (по умолчанию 500)
                $statusCode = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;

                return response()->json([
                    'success' => false,
                    'statusCode' => $statusCode,
                    'data' => [
                        'message' => $e->getMessage() ?: 'Server Error',
                        // Включаем ошибки валидации, если это ValidationException
                        'errors' => method_exists($e, 'errors') ? $e->errors() : null,
                    ],

                ], $statusCode);
            }
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
