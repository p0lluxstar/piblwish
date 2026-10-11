<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Recaller;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

// Скользящий срок «Запомнить меня»: пока пользователь заходит, cookie
// remember_web_* выдаётся заново на полный срок (AUTH_REMEMBER_DAYS),
// поэтому вход теряется только после стольких дней без визитов.
// Laravel сам срок не продлевает: он отсчитывается от входа с паролем.
class RenewRememberCookie
{
    // Cookie продлевается не чаще раза в сутки на сессию
    private const RENEW_INTERVAL_SECONDS = 24 * 60 * 60;

    private const SESSION_KEY = 'remember_cookie_renewed_at';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        /** @var SessionGuard $guard */
        $guard = Auth::guard('web');
        $user = $guard->user();

        // Пользователя нет и после выхода в этом запросе: продлевать нечего
        if (! $user || ! $request->hasSession()) {
            return $response;
        }

        $session = $request->session();
        $renewedAt = $session->get(self::SESSION_KEY);

        if ($renewedAt && now()->getTimestamp() - $renewedAt < self::RENEW_INTERVAL_SECONDS) {
            return $response;
        }

        $value = $request->cookies->get($guard->getRecallerName());

        if (! is_string($value)) {
            return $response;
        }

        $recaller = new Recaller($value);

        // Продлеваем только действующую cookie этого пользователя: после смены
        // remember_token или пароля старая cookie не оживает, а cookie, только что
        // выданная при смене пароля, не перезаписывается прежней
        $isValid = $recaller->valid()
            && (string) $recaller->id() === (string) $user->getAuthIdentifier()
            && hash_equals((string) $user->getRememberToken(), $recaller->token())
            && hash_equals($guard->hashPasswordForCookie($user->getAuthPassword()), $recaller->hash());

        if (! $isValid) {
            return $response;
        }

        $guard->getCookieJar()->queue(
            $guard->getCookieJar()->make(
                $guard->getRecallerName(),
                $value,
                (int) config('auth.guards.web.remember'),
            )
        );

        $session->put(self::SESSION_KEY, now()->getTimestamp());

        return $response;
    }
}
