<?php

namespace Tests\Feature;

use App\Mail\PasswordChangedMail;
use App\Mail\PasswordResetCodeMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Восстановление пароля: POST /v1/forgot-password и POST /v1/reset-password.
 *
 * Код из письма действует 10 минут. Ответы не должны раскрывать,
 * зарегистрирован ли email.
 */
class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private const FORGOT_MESSAGE = 'Если аккаунт с таким email существует, на него отправлен код восстановления';

    private function forgotPassword(string $email): TestResponse
    {
        return $this->postJson('/v1/forgot-password', ['email' => $email]);
    }

    private function resetPassword(string $email, string $code, array $overrides = []): TestResponse
    {
        return $this->postJson('/v1/reset-password', [
            'email' => $email,
            'code' => $code,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
            ...$overrides,
        ]);
    }

    // Запрашивает код и возвращает его из перехваченного письма
    private function requestCode(User $user): string
    {
        Mail::fake();

        $this->forgotPassword($user->email)->assertOk();

        $code = null;

        Mail::assertQueued(PasswordResetCodeMail::class, function (PasswordResetCodeMail $mail) use (&$code): bool {
            $code = $mail->code;

            return true;
        });

        return $code;
    }

    private function createSession(User $user, string $id): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $user->getKey(),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'payload' => '',
            'last_activity' => now()->getTimestamp(),
        ]);
    }

    public function test_code_is_sent_to_existing_user(): void
    {
        Mail::fake();

        $user = User::factory()->create();

        $this->forgotPassword($user->email)
            ->assertOk()
            ->assertJsonPath('data.message', self::FORGOT_MESSAGE);

        Mail::assertQueued(
            PasswordResetCodeMail::class,
            fn (PasswordResetCodeMail $mail): bool => $mail->hasTo($user->email)
                && $mail->username === $user->username
                && preg_match('/^\d{6}$/', $mail->code) === 1
        );

        // В базе хранится хеш кода, а не сам код
        $code = Mail::queued(PasswordResetCodeMail::class)->first()->code;
        $token = DB::table('password_reset_tokens')->where('email', $user->email)->value('token');

        $this->assertNotSame($code, $token);
        $this->assertTrue(Hash::check($code, $token));
    }

    public function test_response_is_the_same_for_unknown_email(): void
    {
        Mail::fake();

        $this->forgotPassword('nobody@example.com')
            ->assertOk()
            ->assertJsonPath('data.message', self::FORGOT_MESSAGE);

        Mail::assertNothingQueued();
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_code_is_not_sent_to_unverified_or_deleted_user(): void
    {
        Mail::fake();

        $unverified = User::factory()->unverified()->create();
        $deleted = User::factory()->create(['deactivated_at' => now()]);

        $this->forgotPassword($unverified->email)->assertOk();
        $this->forgotPassword($deleted->email)->assertOk();

        Mail::assertNothingQueued();
    }

    public function test_password_is_reset_with_valid_code(): void
    {
        $user = User::factory()->create();
        $code = $this->requestCode($user);

        $this->resetPassword($user->email, $code)
            ->assertOk()
            ->assertJsonPath('data.message', 'Пароль изменён');

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
    }

    public function test_login_works_only_with_new_password(): void
    {
        $user = User::factory()->create();
        $code = $this->requestCode($user);

        $this->resetPassword($user->email, $code)->assertOk();

        $this->postJson('/v1/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertStatus(401);

        $this->postJson('/v1/login', [
            'email' => $user->email,
            'password' => 'new-password',
        ])->assertOk();
    }

    public function test_all_sessions_and_tokens_are_deleted(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->createSession($user, 'user-session');
        $this->createSession($otherUser, 'other-user-session');
        $user->createToken('device');

        $code = $this->requestCode($user);

        $this->resetPassword($user->email, $code)->assertOk();

        $this->assertDatabaseMissing('sessions', ['id' => 'user-session']);
        // Сессии других пользователей не затрагиваются
        $this->assertDatabaseHas('sessions', ['id' => 'other-user-session']);
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_notification_email_is_queued_after_reset(): void
    {
        $user = User::factory()->create();
        $code = $this->requestCode($user);

        $this->resetPassword($user->email, $code)->assertOk();

        Mail::assertQueued(
            PasswordChangedMail::class,
            fn (PasswordChangedMail $mail): bool => $mail->hasTo($user->email)
        );
    }

    public function test_wrong_code_is_rejected(): void
    {
        $user = User::factory()->create();
        $code = $this->requestCode($user);

        $wrongCode = $code === '111111' ? '222222' : '111111';

        $this->resetPassword($user->email, $wrongCode)
            ->assertStatus(400)
            ->assertJsonPath('data.message', 'Неверный или просроченный код');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_expired_code_is_rejected(): void
    {
        $user = User::factory()->create();
        $code = $this->requestCode($user);

        $this->travel(11)->minutes();

        $this->resetPassword($user->email, $code)->assertStatus(400);

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_code_cannot_be_used_twice(): void
    {
        $user = User::factory()->create();
        $code = $this->requestCode($user);

        $this->resetPassword($user->email, $code)->assertOk();

        $this->resetPassword($user->email, $code, [
            'password' => 'another-password',
            'password_confirmation' => 'another-password',
        ])->assertStatus(400);
    }

    public function test_new_code_replaces_previous_one(): void
    {
        $user = User::factory()->create();
        $firstCode = $this->requestCode($user);

        // Повторный запрос для того же email допускается не чаще раза в минуту
        $this->travel(61)->seconds();
        $secondCode = $this->requestCode($user);

        if ($firstCode !== $secondCode) {
            $this->resetPassword($user->email, $firstCode)->assertStatus(400);
        }

        $this->resetPassword($user->email, $secondCode)->assertOk();
    }

    public function test_code_of_another_user_is_rejected(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $code = $this->requestCode($otherUser);

        $this->resetPassword($user->email, $code)->assertStatus(400);
    }

    public function test_unknown_email_gets_the_same_error_as_wrong_code(): void
    {
        $this->resetPassword('nobody@example.com', '123456')
            ->assertStatus(400)
            ->assertJsonPath('data.message', 'Неверный или просроченный код');
    }

    public function test_reset_validation(): void
    {
        $user = User::factory()->create();

        $this->resetPassword($user->email, '12ab', [
            'password' => 'short',
            'password_confirmation' => 'other',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code', 'password'], 'data.errors');
    }

    public function test_code_request_is_limited_per_email(): void
    {
        $user = User::factory()->create();

        $this->forgotPassword($user->email)->assertOk();
        $this->forgotPassword($user->email)->assertStatus(429);

        // Другой email с того же IP не заблокирован
        $this->forgotPassword('other@example.com')->assertOk();
    }

    public function test_reset_attempts_are_limited_per_email(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->resetPassword($user->email, '000000')->assertStatus(400);
        }

        $this->resetPassword($user->email, '000000')->assertStatus(429);
    }

    public function test_reset_code_email_renders(): void
    {
        $mail = new PasswordResetCodeMail(
            username: 'john_doe',
            code: '123456',
            expiresInMinutes: 10,
        );

        $mail->assertHasSubject('Код восстановления пароля');
        $mail->assertSeeInHtml('john_doe');
        $mail->assertSeeInHtml('123456');
        $mail->assertSeeInHtml('10 минут');
    }
}
