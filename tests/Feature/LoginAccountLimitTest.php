<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Лимит попыток входа в один аккаунт (AuthService::login).
 *
 * Лимиты маршрута считаются по email и IP и обходятся сменой IP или
 * написанием email с другой диакритикой, которое MySQL считает тем же
 * адресом. Отключённый лимитер маршрута моделирует этот обход.
 */
class LoginAccountLimitTest extends TestCase
{
    use RefreshDatabase;

    private const LOCKED_MESSAGE = 'Слишком много попыток входа. Попробуйте через 15 минут или восстановите пароль';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function login(string $email, string $password): TestResponse
    {
        return $this->postJson('/v1/login', [
            'email' => $email,
            'password' => $password,
        ]);
    }

    public function test_account_is_locked_after_ten_failed_attempts(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 10; $i++) {
            $this->login($user->email, 'wrong-password')->assertStatus(401);
        }

        $this->login($user->email, 'wrong-password')
            ->assertStatus(429)
            ->assertJsonPath('data.message', self::LOCKED_MESSAGE);

        // Сверх лимита верный пароль получает тот же ответ: перебор не узнаёт,
        // какой пароль подошёл
        $this->login($user->email, 'password')->assertStatus(429);
        $this->assertGuest('web');

        $this->travel(15)->minutes();
        $this->travel(1)->seconds();

        $this->login($user->email, 'password')->assertOk();
    }

    public function test_successful_login_resets_counter(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 9; $i++) {
            $this->login($user->email, 'wrong-password')->assertStatus(401);
        }

        $this->login($user->email, 'password')->assertOk();
        $this->post('/v1/logout');

        for ($i = 0; $i < 10; $i++) {
            $this->login($user->email, 'wrong-password')->assertStatus(401);
        }
    }

    public function test_lock_does_not_affect_other_accounts(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        for ($i = 0; $i < 11; $i++) {
            $this->login($user->email, 'wrong-password');
        }

        $this->login($user->email, 'password')->assertStatus(429);
        $this->login($otherUser->email, 'password')->assertOk();
    }

    // Неизвестный email блокируется так же, как существующий,
    // поэтому ответ 429 не выдаёт, зарегистрирован ли адрес
    public function test_unknown_email_is_locked_the_same_way(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->login('nobody@example.com', 'wrong-password')->assertStatus(401);
        }

        $this->login('nobody@example.com', 'wrong-password')
            ->assertStatus(429)
            ->assertJsonPath('data.message', self::LOCKED_MESSAGE);
    }
}
