<?php

namespace Tests\Feature;

use App\Http\Middleware\AssignRequestContext;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Monolog\Handler\TestHandler;
use Tests\TestCase;

/**
 * Контекст запроса в журнале: middleware AssignRequestContext.
 *
 * Каждая запись журнала содержит request_id запроса и user_id вошедшего
 * пользователя, а request_id возвращается в заголовке X-Request-Id.
 */
class RequestContextTest extends TestCase
{
    use RefreshDatabase;

    private TestHandler $logHandler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logHandler = new TestHandler;
        Log::driver()->getLogger()->pushHandler($this->logHandler);

        // GET перехватил бы SPA-маршрут /{any}, зарегистрированный раньше
        Route::middleware('web')->post('/_test/log', function () {
            Log::info('test entry');

            return response()->json(['ok' => true]);
        });

        Route::middleware('api')->get('/api/_test/log', function () {
            Log::info('test entry');

            return response()->json(['ok' => true]);
        });
    }

    private function lastLogExtra(): array
    {
        $records = $this->logHandler->getRecords();
        $this->assertNotEmpty($records);

        return end($records)->extra;
    }

    public function test_response_has_request_id_header(): void
    {
        $response = $this->postJson('/_test/log')->assertOk();

        $requestId = $response->headers->get(AssignRequestContext::HEADER);

        $this->assertTrue(Str::isUuid($requestId));
        $this->assertSame($requestId, $this->lastLogExtra()['request_id']);
    }

    public function test_each_request_gets_new_request_id(): void
    {
        $first = $this->postJson('/_test/log')->headers->get(AssignRequestContext::HEADER);
        $second = $this->postJson('/_test/log')->headers->get(AssignRequestContext::HEADER);

        $this->assertNotSame($first, $second);
    }

    public function test_log_entry_has_user_id_of_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/_test/log')->assertOk();

        $this->assertSame($user->id, $this->lastLogExtra()['user_id']);
    }

    public function test_guest_log_entry_has_no_user_id(): void
    {
        $user = User::factory()->create();

        // user_id предыдущего запроса не должен остаться в контексте
        $this->actingAs($user)->postJson('/_test/log')->assertOk();
        $this->app['auth']->forgetGuards();

        $this->postJson('/_test/log')->assertOk();

        $this->assertArrayNotHasKey('user_id', $this->lastLogExtra());
    }

    public function test_api_routes_have_request_context(): void
    {
        $response = $this->getJson('/api/_test/log')->assertOk();

        $this->assertSame(
            $response->headers->get(AssignRequestContext::HEADER),
            $this->lastLogExtra()['request_id'],
        );
    }
}
