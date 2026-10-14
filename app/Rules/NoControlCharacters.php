<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Email без управляющих символов (\r, \n, \0, табуляция и т. п.) и без
 * разделителей строк Unicode.
 *
 * Правило 'email' пропускает перевод строки в кавычках
 * («"a\r\nBcc: …"@example.com»), а laravel/framework до 13.10 и symfony/mime
 * до 8.0.12 вставляют такой адрес в заголовок письма как есть
 * (CVE-2026-48019, CVE-2026-45067). Получается лишняя строка заголовка,
 * например Bcc со списком чужих адресов. Правило закрывает это независимо
 * от версий пакетов.
 */
class NoControlCharacters implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Тип проверяют другие правила поля
        if (! is_string($value)) {
            return;
        }

        // Некорректный UTF-8 preg_match не разбирает и возвращает false:
        // такая строка тоже отклоняется
        if (preg_match('/[\p{Cc}\p{Zl}\p{Zp}]/u', $value) !== 0) {
            $fail('Email содержит недопустимые символы');
        }
    }
}
