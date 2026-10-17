<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

// Страница /log-viewer для тех, кому журналы недоступны, выглядит как
// несуществующий адрес: SPA показывает свою страницу 404 с шапкой и подвалом,
// и снаружи не видно, что по адресу есть закрытый раздел.
// API журналов (log-viewer.api_middleware) по-прежнему отвечает JSON 403
class HideLogViewerPage
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Gate::denies('viewLogViewer')) {
            // Пишутся только попытки вошедших пользователей: адрес пакета известен
            // сканерам, и записи о гостях были бы в основном от ботов.
            // ID пользователя и request_id дописывает Context (AssignRequestContext)
            if ($request->user() !== null) {
                Log::warning('Log viewer access denied');
            }

            return response()->view('welcome', ['noindex' => true], 404);
        }

        return $next($request);
    }
}
