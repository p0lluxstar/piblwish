<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Проверка обработчика исключений из bootstrap/app.php:
 * JSON-ошибки приходят с правильным HTTP-статусом
 * и в формате { success: false, statusCode, data: { message, errors } }.
 */
class ApiErrorResponseTest extends TestCase
{
    use RefreshDatabase;

    private function assertErrorFormat(TestResponse $response, int $status): void
    {
        $response->assertStatus($status)
            ->assertJsonPath('success', false)
            ->assertJsonPath('statusCode', $status)
            ->assertJsonStructure(['data' => ['message', 'errors']]);
    }

    public function test_validation_error_returns_422_with_errors(): void
    {
        $response = $this->postJson('/v1/register', []);

        $this->assertErrorFormat($response, 422);
        $response->assertJsonValidationErrors(['username', 'email', 'password'], 'data.errors');
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $response = $this->getJson('/v1/wishlists');

        $this->assertErrorFormat($response, 401);
        $response->assertJsonPath('data.errors', null);
    }

    public function test_wrong_credentials_return_401_with_message(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/v1/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertErrorFormat($response, 401);
        $response->assertJsonPath('data.message', 'Неверный email или пароль.');
    }

    public function test_missing_model_returns_404(): void
    {
        $response = $this->getJson('/api/v1/shared-wishlists/01JZZZZZZZZZZZZZZZZZZZZZZZ');

        $this->assertErrorFormat($response, 404);
    }

    public function test_unknown_api_route_returns_404(): void
    {
        $this->assertErrorFormat($this->getJson('/api/v1/unknown'), 404);
    }

    public function test_too_many_requests_returns_429_with_retry_after(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/v1/register', []);
        }

        $response = $this->postJson('/v1/register', []);

        $this->assertErrorFormat($response, 429);
        $response->assertHeader('Retry-After');
    }

    public function test_server_error_message_is_hidden_without_debug(): void
    {
        config(['app.debug' => false]);

        $this->app['router']->get('/v1/test-server-error', function (): never {
            throw new \RuntimeException('SQLSTATE: секретные детали');
        });

        $response = $this->getJson('/v1/test-server-error');

        $this->assertErrorFormat($response, 500);
        $response->assertJsonPath('data.message', 'Server Error');
    }
}
