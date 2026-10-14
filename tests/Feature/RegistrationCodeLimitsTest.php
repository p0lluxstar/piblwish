<?php

namespace Tests\Feature;

use App\Mail\VerificationCodeMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Защита кода подтверждения регистрации от перебора.
 *
 * Лимиты маршрутов считаются по введённому email и обходятся написанием
 * с другой диакритикой, которое MySQL считает тем же адресом. Поэтому
 * число проверок одного кода и число выданных кодов ограничены по самому
 * коду и аккаунту. Отключённый лимитер маршрутов моделирует этот обход.
 */
class RegistrationCodeLimitsTest extends TestCase
{
    use RefreshDatabase;

    private const EXHAUSTED_MESSAGE = 'Слишком много неверных попыток. Запросите новый код';

    private function register(): User
    {
        $this->postJson('/v1/register', [
            'username' => 'newuser',
            'email' => 'new@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSuccessful();

        return User::where('email', 'new@example.com')->firstOrFail();
    }

    private function verify(User $user, string $code): TestResponse
    {
        return $this->postJson('/v1/verify-registration', [
            'email' => $user->email,
            'code' => $code,
        ]);
    }

    private function resend(User $user): TestResponse
    {
        return $this->postJson('/v1/verify-registration/resend', ['email' => $user->email]);
    }

    // Код из последнего перехваченного письма
    private function lastCode(): string
    {
        return Mail::queued(VerificationCodeMail::class)->last()->code;
    }

    private function wrongCode(string $code): string
    {
        return $code === '111111' ? '222222' : '111111';
    }

    public function test_code_is_annulled_after_five_wrong_attempts(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        Mail::fake();

        $user = $this->register();
        $code = $this->lastCode();

        for ($i = 0; $i < 4; $i++) {
            $this->verify($user, $this->wrongCode($code))
                ->assertStatus(400)
                ->assertJsonPath('data.message', 'Неверный код');
        }

        $this->verify($user, $this->wrongCode($code))
            ->assertStatus(400)
            ->assertJsonPath('data.message', self::EXHAUSTED_MESSAGE);

        $this->assertSame(0, $user->verificationRegistrationCodes()->count());

        // Верный код после аннулирования уже не подходит
        $this->verify($user, $code)->assertStatus(404);

        $this->assertFalse($user->fresh()->is_active);
    }

    public function test_correct_code_works_after_four_wrong_attempts(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        Mail::fake();

        $user = $this->register();
        $code = $this->lastCode();

        for ($i = 0; $i < 4; $i++) {
            $this->verify($user, $this->wrongCode($code))->assertStatus(400);
        }

        $this->verify($user, $code)->assertOk();

        $this->assertTrue($user->fresh()->is_active);
    }

    public function test_new_code_gets_fresh_attempts(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        Mail::fake();

        $user = $this->register();
        $firstCode = $this->lastCode();

        for ($i = 0; $i < 5; $i++) {
            $this->verify($user, $this->wrongCode($firstCode))->assertStatus(400);
        }

        $this->travel(61)->seconds();
        $this->resend($user)->assertOk();

        $this->assertSame(0, $user->verificationRegistrationCodes()->value('attempts'));

        $this->verify($user, $this->lastCode())->assertOk();
    }

    public function test_codes_are_issued_once_a_minute_per_account(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        Mail::fake();

        $user = $this->register();
        $code = $this->lastCode();

        // Повторная отправка и вход с верным паролем в ту же минуту
        // нового кода не выдают, прежний продолжает действовать
        $this->resend($user)->assertOk();
        $this->postJson('/v1/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertStatus(403);

        Mail::assertQueuedCount(1);

        $this->verify($user, $code)->assertOk();
    }

    public function test_codes_are_limited_per_account_per_day(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        Mail::fake();

        $user = $this->register();

        for ($i = 0; $i < 9; $i++) {
            $this->travel(61)->seconds();
            $this->resend($user)->assertOk();
        }

        Mail::assertQueuedCount(10);

        $this->travel(61)->seconds();
        $this->resend($user)->assertOk();

        Mail::assertQueuedCount(10);

        $this->travel(1)->days();
        $this->resend($user)->assertOk();

        Mail::assertQueuedCount(11);
    }
}
