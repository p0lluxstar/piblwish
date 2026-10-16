<?php

namespace App\Support;

use Illuminate\Support\Facades\Context;
use Sentry\Breadcrumb;
use Sentry\Event;
use Sentry\EventHint;
use Sentry\UserDataBag;
use Throwable;
use WeakMap;

// Обработчики событий Sentry (config/sentry.php). Методы статические,
// чтобы конфигурацию можно было кешировать (config:cache не сохраняет замыкания)
final class SentryEventFilter
{
    // Токен общей ссылки открывает список, поэтому в Sentry он не уходит:
    // /shared-wishlist/<токен> (страница SPA), /api/v1/shared-wishlists/<токен>,
    // /v1/saved-wishlists/<токен> (закладки) → :token
    private const SHARE_TOKEN_PATTERN = '~(/(?:shared-wishlists?|saved-wishlists)/)[^/?#\s"\']+~i';

    // Подпись ссылки отписки от напоминаний: с ней отписку выполнит кто угодно
    private const SIGNATURE_PATTERN = '~([?&]signature=)[^&#\s"\']+~i';

    private const MAX_DEPTH = 8;

    /**
     * Исключения, уже отправленные в Sentry в этом процессе.
     *
     * @var WeakMap<Throwable, true>|null
     */
    private static ?WeakMap $reported = null;

    public static function beforeSend(Event $event, ?EventHint $hint = null): ?Event
    {
        // Исключение отправляют и обработчик ошибок (Integration::handles),
        // и канал журнала sentry, куда Laravel записывает то же исключение.
        // Второе событие отбрасывается, а Log::error с перехваченным
        // исключением, которого нет в обработчике, отправляется один раз
        $exception = $hint?->exception;

        if ($exception instanceof Throwable) {
            self::$reported ??= new WeakMap;

            if (isset(self::$reported[$exception])) {
                return null;
            }

            self::$reported[$exception] = true;
        }

        self::attachContext($event);

        return self::scrubEvent($event);
    }

    public static function beforeSendTransaction(Event $event, ?EventHint $hint = null): ?Event
    {
        self::attachContext($event);

        foreach ($event->getSpans() as $span) {
            $description = $span->getDescription();

            if ($description !== null) {
                $span->setDescription(self::scrubString($description));
            }

            $span->setData(self::scrub($span->getData()));
            $span->setTags(self::scrub($span->getTags()));
        }

        return self::scrubEvent($event);
    }

    public static function beforeBreadcrumb(Breadcrumb $breadcrumb): ?Breadcrumb
    {
        $message = $breadcrumb->getMessage();

        if ($message !== null) {
            $breadcrumb = $breadcrumb->withMessage(self::scrubString($message));
        }

        foreach ($breadcrumb->getMetadata() as $key => $value) {
            $breadcrumb = $breadcrumb->withMetadata($key, self::scrub($value));
        }

        return $breadcrumb;
    }

    // Сбор данных о пользователе в Sentry выключен (data_collection.user_info):
    // иначе SDK отправляет email и IP. Передаётся только ID из Context,
    // который заполняет AssignRequestContext и Laravel переносит в задачи очереди
    private static function attachContext(Event $event): void
    {
        $userId = Context::get('user_id');

        if ($userId !== null && $event->getUser() === null) {
            $event->setUser(UserDataBag::createFromUserIdentifier((string) $userId));
        }

        $requestId = Context::get('request_id');

        if (is_string($requestId)) {
            $event->setTag('request_id', $requestId);
        }
    }

    private static function scrubEvent(Event $event): Event
    {
        $event->setRequest(self::scrub($event->getRequest()));
        $event->setTags(self::scrub($event->getTags()));
        $event->setExtra(self::scrub($event->getExtra()));

        foreach ($event->getContexts() as $name => $data) {
            $event->setContext($name, self::scrub($data));
        }

        $transaction = $event->getTransaction();

        if ($transaction !== null) {
            $event->setTransaction(self::scrubString($transaction));
        }

        $message = $event->getMessage();

        if ($message !== null) {
            $formatted = $event->getMessageFormatted();

            $event->setMessage(
                self::scrubString($message),
                self::scrub($event->getMessageParams()),
                $formatted === null ? null : self::scrubString($formatted),
            );
        }

        foreach ($event->getExceptions() as $exception) {
            $exception->setValue(self::scrubString($exception->getValue()));
        }

        return $event;
    }

    /**
     * @template T
     *
     * @param  T  $value
     * @return T
     */
    private static function scrub(mixed $value, int $depth = 0): mixed
    {
        if (is_string($value)) {
            return self::scrubString($value);
        }

        if (! is_array($value) || $depth >= self::MAX_DEPTH) {
            return $value;
        }

        foreach ($value as $key => $item) {
            $value[$key] = self::scrub($item, $depth + 1);
        }

        return $value;
    }

    private static function scrubString(string $value): string
    {
        return (string) preg_replace(
            [self::SHARE_TOKEN_PATTERN, self::SIGNATURE_PATTERN],
            ['$1:token', '$1:signature'],
            $value,
        );
    }
}
