<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        JsonResource::withoutWrapping();

        $this->configureRateLimiting();
    }

    /**
     * Именованные лимитеры запросов.
     *
     * Ключ именованного лимитера включает его имя, поэтому у каждой группы
     * маршрутов собственный счётчик. Анонимный throttle:N,M строит ключ
     * только из IP (или ID пользователя), и все такие маршруты делят один счётчик.
     */
    private function configureRateLimiting(): void
    {
        // Гость определяется по IP, авторизованный пользователь — по ID
        $byUserOrIp = fn (Request $request): string => (string) ($request->user()?->getAuthIdentifier() ?? $request->ip());

        // Вход: подбор пароля к одному аккаунту и перебор аккаунтов с одного IP
        RateLimiter::for('login', fn (Request $request): array => [
            Limit::perMinute(5)->by(mb_strtolower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perMinute(20)->by($request->ip()),
        ]);

        // Регистрация: каждая попытка отправляет письмо
        RateLimiter::for('registration', fn (Request $request): array => [
            Limit::perMinute(3)->by($request->ip()),
            Limit::perHour(20)->by($request->ip()),
        ]);

        // Подтверждение кода: перебор 6-значного кода для одного email
        RateLimiter::for('verify-code', fn (Request $request): array => [
            Limit::perMinute(5)->by(mb_strtolower((string) $request->input('email'))),
            Limit::perMinute(20)->by($request->ip()),
        ]);

        // Расшаренные списки: чтение допускается чаще, чем отметка позиций
        RateLimiter::for('shared-read', fn (Request $request): Limit => Limit::perMinute(60)->by($byUserOrIp($request)));
        RateLimiter::for('shared-write', fn (Request $request): Limit => Limit::perMinute(10)->by($byUserOrIp($request)));

        // Дашборд: маршруты под auth:sanctum
        RateLimiter::for('dashboard', fn (Request $request): Limit => Limit::perMinute(60)->by($byUserOrIp($request)));
    }
}
