<?php

namespace Tests\Feature;

use App\Support\SentryEventFilter;
use Illuminate\Support\Facades\Context;
use RuntimeException;
use Sentry\Breadcrumb;
use Sentry\Event;
use Sentry\EventHint;
use Sentry\ExceptionDataBag;
use Sentry\Tracing\Span;
use Tests\TestCase;

class SentryEventFilterTest extends TestCase
{
    private const TOKEN = '01kqpy220sn61vgfvwryw9p90j';

    public function test_share_token_is_removed_from_error_event(): void
    {
        $event = Event::createEvent();
        $event->setRequest([
            'url' => 'https://piblwish.ru/api/v1/shared-wishlists/'.self::TOKEN.'/items',
            'method' => 'PATCH',
        ]);
        $event->setTransaction('/shared-wishlist/'.self::TOKEN);
        $event->setExceptions([
            new ExceptionDataBag(new RuntimeException('Ошибка в /v1/saved-wishlists/'.self::TOKEN)),
        ]);
        $event->setMessage('Список /shared-wishlist/'.self::TOKEN.' недоступен');
        $event->setExtra(['log_context' => ['url' => '/shared-wishlist/'.self::TOKEN]]);

        $event = SentryEventFilter::beforeSend($event, EventHint::fromArray([]));

        $this->assertSame('https://piblwish.ru/api/v1/shared-wishlists/:token/items', $event->getRequest()['url']);
        $this->assertSame('/shared-wishlist/:token', $event->getTransaction());
        $this->assertSame('Ошибка в /v1/saved-wishlists/:token', $event->getExceptions()[0]->getValue());
        $this->assertSame('Список /shared-wishlist/:token недоступен', $event->getMessage());
        $this->assertStringNotContainsString(self::TOKEN, json_encode($event->getExtra()));
    }

    public function test_share_token_is_removed_from_spans(): void
    {
        $span = new Span;
        $span->setDescription('GET /api/v1/shared-wishlists/'.self::TOKEN);
        $span->setData(['url' => 'https://piblwish.ru/shared-wishlist/'.self::TOKEN]);

        $event = Event::createTransaction();
        $event->setTransaction('/{any}');
        $event->setSpans([$span]);

        SentryEventFilter::beforeSendTransaction($event);

        $this->assertSame('GET /api/v1/shared-wishlists/:token', $span->getDescription());
        $this->assertSame('https://piblwish.ru/shared-wishlist/:token', $span->getData('url'));
    }

    public function test_share_token_and_signature_are_removed_from_breadcrumbs(): void
    {
        $breadcrumb = new Breadcrumb(
            Breadcrumb::LEVEL_INFO,
            Breadcrumb::TYPE_HTTP,
            'http',
            'POST /v1/saved-wishlists/'.self::TOKEN,
            ['url' => '/email-reminders/unsubscribe/1?expires=1&signature=abc123'],
        );

        $breadcrumb = SentryEventFilter::beforeBreadcrumb($breadcrumb);

        $this->assertSame('POST /v1/saved-wishlists/:token', $breadcrumb->getMessage());
        $this->assertSame(
            '/email-reminders/unsubscribe/1?expires=1&signature=:signature',
            $breadcrumb->getMetadata()['url'],
        );
    }

    // Исключение из обработчика ошибок Laravel ещё и записывает в журнал,
    // откуда его отправляет канал sentry: второе событие не нужно
    public function test_same_exception_is_sent_once(): void
    {
        $exception = new RuntimeException('Сбой');
        $hint = EventHint::fromArray(['exception' => $exception]);

        $this->assertNotNull(SentryEventFilter::beforeSend(Event::createEvent(), $hint));
        $this->assertNull(SentryEventFilter::beforeSend(Event::createEvent(), $hint));

        $other = EventHint::fromArray(['exception' => new RuntimeException('Сбой')]);

        $this->assertNotNull(SentryEventFilter::beforeSend(Event::createEvent(), $other));
    }

    public function test_user_id_and_request_id_are_taken_from_context(): void
    {
        Context::add('user_id', self::TOKEN);
        Context::add('request_id', 'b6f1c1d2-0000-4000-8000-000000000000');

        $event = SentryEventFilter::beforeSend(Event::createEvent());

        $this->assertSame(self::TOKEN, $event->getUser()?->getId());
        $this->assertNull($event->getUser()?->getEmail());
        $this->assertNull($event->getUser()?->getIpAddress());
        $this->assertSame('b6f1c1d2-0000-4000-8000-000000000000', $event->getTags()['request_id']);
    }

    public function test_guest_event_has_no_user(): void
    {
        $event = SentryEventFilter::beforeSend(Event::createEvent());

        $this->assertNull($event->getUser());
    }
}
