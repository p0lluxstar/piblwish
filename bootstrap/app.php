<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\TransformApiResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

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

        // Приводим JSON-ответ об ошибке к формату { success, statusCode, data }.
        // respond() вызывается после стандартного рендеринга Laravel, поэтому
        // статус уже определён фреймворком (401, 403, 404, 422, 429...),
        // заголовки (например, Retry-After) сохранены, а текст ошибок 5xx
        // скрыт при APP_DEBUG=false.
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            if (! $request->expectsJson()) {
                return $response;
            }

            $statusCode = $response->getStatusCode();
            $payload = json_decode((string) $response->getContent(), true);

            return response()->json([
                'success' => false,
                'statusCode' => $statusCode,
                'data' => [
                    'message' => $payload['message'] ?? 'Server Error',
                    // Заполняется только для ошибок валидации (422)
                    'errors' => $payload['errors'] ?? null,
                ],
            ], $statusCode, $response->headers->all());
        });
    })->create();
