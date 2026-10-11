<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Ссылка на сбор: только https и только на платформу из config('wishlist.fund_hosts').
 *
 * Хост должен совпадать с разрешённым доменом или быть его поддоменом. Сравнение
 * идёт с точкой перед доменом, поэтому evilcloudtips.ru не проходит как cloudtips.ru.
 */
class AllowedFundUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! self::passes($value)) {
            $fail(self::message());
        }
    }

    public static function passes(string $url): bool
    {
        if (strtolower((string) parse_url($url, PHP_URL_SCHEME)) !== 'https') {
            return false;
        }

        $host = rtrim(strtolower((string) parse_url($url, PHP_URL_HOST)), '.');

        if ($host === '') {
            return false;
        }

        foreach (array_keys(config('wishlist.fund_hosts')) as $allowed) {
            if ($host === $allowed || str_ends_with($host, '.'.$allowed)) {
                return true;
            }
        }

        return false;
    }

    public static function message(): string
    {
        $platforms = implode(', ', array_unique(config('wishlist.fund_hosts')));

        return "Ссылки на сбор принимаются только с сайтов: {$platforms}";
    }
}
