<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

// Контекст запроса для журнала: Laravel добавляет данные Context к каждой
// записи и передаёт их в задачи очереди, поэтому по request_id находятся
// все записи одного запроса, включая записи контейнера queue.
// Идентификатор возвращается в заголовке X-Request-Id.
class AssignRequestContext
{
    public const HEADER = 'X-Request-Id';

    public function handle(Request $request, Closure $next): Response
    {
        $requestId = (string) Str::uuid();

        Context::add('request_id', $requestId);

        // Пользователь, вошедший в этом запросе, в контекст не попадает:
        // при начале запроса он ещё неизвестен
        $userId = Auth::id();

        if ($userId !== null) {
            Context::add('user_id', $userId);
        } else {
            Context::forget('user_id');
        }

        $response = $next($request);

        $response->headers->set(self::HEADER, $requestId);

        return $response;
    }
}
