<?php

namespace Tests\Feature;

use App\Mail\EmailChangeCodeMail;
use App\Mail\EmailChangedMail;
use App\Models\EmailChangeRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Смена email: POST /v1/user/email (код на новый адрес)
 * и POST /v1/user/email/confirm (подтверждение кодом).
 */
class ChangeEmailTest extends TestCase
{
    use RefreshDatabase;

    private const NEW_EMAIL = 'new@example.com';

    private function requestChange(User $user, array $overrides = []): TestResponse
    {
        return $this->actingAs($user)->postJson('/v1/user/email', [
            'email' => self::NEW_EMAIL,
            'current_password' => 'password',
            ...$overrides,
        ]);
    }

    private function confirmChange(User $user, string $code): TestResponse
    {
        return $this->actingAs($user)->postJson('/v1/user/email/confirm', [
            'code' => $code,
        ]);
    }

    // Запрос на смену с известным кодом, без обращения к API
    private function createChangeRequest(User $user, array $overrides = []): EmailChangeRequest
    {
        return $user->emailChangeRequest()->create([
            'new_email' => self::NEW_EMAIL,
            'code_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(15),
            ...$overrides,
        ]);
    }

    // Код из письма, поставленного в очередь при запросе смены
    private function queuedCode(): string
    {
        $code = null;

        Mail::assertQueued(EmailChangeCodeMail::class, function (EmailChangeCodeMail $mail) use (&$code): bool {
            $code = $mail->code;

            return true;
        });

        return $code;
    }

    public function test_email_is_changed_after_confirmation(): void
    {
        Mail::fake();

        $user = User::factory()->create();

        $this->requestChange($user)
            ->assertOk()
            ->assertJsonPath('data.message', 'Код отправлен на новый email');

        // До подтверждения адрес прежний
        $this->assertNotSame(self::NEW_EMAIL, $user->fresh()->email);

        $this->confirmChange($user, $this->queuedCode())
            ->assertOk()
            ->assertJsonPath('data.email', self::NEW_EMAIL);

        $this->assertSame(self::NEW_EMAIL, $user->fresh()->email);
        $this->assertDatabaseMissing('email_change_requests', ['user_id' => $user->getKey()]);
    }

    public function test_code_is_sent_to_new_email(): void
    {
        Mail::fake();

        $user = User::factory()->create();

        $this->requestChange($user)->assertOk();

        Mail::assertQueued(
            EmailChangeCodeMail::class,
            fn (EmailChangeCodeMail $mail): bool => $mail->hasTo(self::NEW_EMAIL)
                && ! $mail->hasTo($user->email)
                && $mail->username === $user->username
        );
    }

    public function test_code_is_stored_as_hash(): void
    {
        Mail::fake();

        $user = User::factory()->create();

        $this->requestChange($user)->assertOk();

        $changeRequest = $user->emailChangeRequest()->first();

        $this->assertNotSame($this->queuedCode(), $changeRequest->code_hash);
        $this->assertTrue(Hash::check($this->queuedCode(), $changeRequest->code_hash));
    }

    public function test_notification_is_sent_to_old_email(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $oldEmail = $user->email;
        $this->createChangeRequest($user);

        $this->confirmChange($user, '123456')->assertOk();

        Mail::assertQueued(
            EmailChangedMail::class,
            fn (EmailChangedMail $mail): bool => $mail->hasTo($oldEmail)
                && $mail->newEmail === self::NEW_EMAIL
        );
    }

    public function test_mails_render(): void
    {
        $codeMail = new EmailChangeCodeMail(
            username: 'john_doe',
            code: '123456',
            expiresInMinutes: 15,
        );

        $codeMail->assertHasSubject('Код подтверждения нового email');
        $codeMail->assertSeeInHtml('john_doe');
        $codeMail->assertSeeInHtml('123456');

        $changedMail = new EmailChangedMail(
            username: 'john_doe',
            newEmail: self::NEW_EMAIL,
            changedAt: '07.10.2026 12:00 (МСК)',
            ipAddress: '127.0.0.1',
        );

        $changedMail->assertHasSubject('Email аккаунта изменён');
        $changedMail->assertSeeInHtml(self::NEW_EMAIL);
        $changedMail->assertSeeInHtml('07.10.2026 12:00 (МСК)');
        $changedMail->assertSeeInHtml('127.0.0.1');
    }

    public function test_wrong_current_password_is_rejected(): void
    {
        Mail::fake();

        $user = User::factory()->create();

        $this->requestChange($user, ['current_password' => 'wrong-password'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['current_password'], 'data.errors');

        $this->assertDatabaseMissing('email_change_requests', ['user_id' => $user->getKey()]);
        Mail::assertNothingQueued();
    }

    public function test_taken_email_is_rejected(): void
    {
        $user = User::factory()->create();
        User::factory()->create(['email' => self::NEW_EMAIL]);

        $this->requestChange($user)
            ->assertStatus(422)
            ->assertJsonPath('data.errors.email.0', 'Такой email уже зарегистрирован');
    }

    public function test_current_email_is_rejected_regardless_of_case(): void
    {
        $user = User::factory()->create(['email' => 'john@example.com']);

        $this->requestChange($user, ['email' => 'John@Example.com'])
            ->assertStatus(422)
            ->assertJsonPath('data.errors.email.0', 'Это ваш текущий email');
    }

    public function test_invalid_email_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->requestChange($user, ['email' => 'not-an-email'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email'], 'data.errors');
    }

    public function test_new_request_replaces_previous(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $this->createChangeRequest($user, ['attempts' => 3]);

        $this->requestChange($user, ['email' => 'another@example.com'])->assertOk();

        $changeRequest = $user->emailChangeRequest()->first();

        $this->assertSame(1, EmailChangeRequest::where('user_id', $user->getKey())->count());
        $this->assertSame('another@example.com', $changeRequest->new_email);
        $this->assertSame(0, $changeRequest->attempts);

        // Код прежнего запроса больше не действует
        $this->confirmChange($user, '123456')->assertStatus(400);
    }

    public function test_request_is_removed_when_mail_cannot_be_queued(): void
    {
        $user = User::factory()->create();

        Mail::shouldReceive('to')->andThrow(new \RuntimeException('Queue is down'));

        $this->requestChange($user)->assertStatus(503);

        $this->assertDatabaseMissing('email_change_requests', ['user_id' => $user->getKey()]);
    }

    public function test_wrong_code_is_rejected_and_counted(): void
    {
        $user = User::factory()->create();
        $this->createChangeRequest($user);

        $this->confirmChange($user, '000000')
            ->assertStatus(400)
            ->assertJsonPath('data.message', 'Неверный код');

        $this->assertNotSame(self::NEW_EMAIL, $user->fresh()->email);
        $this->assertSame(1, $user->emailChangeRequest()->first()->attempts);
    }

    public function test_request_is_removed_after_too_many_attempts(): void
    {
        $user = User::factory()->create();
        $this->createChangeRequest($user);

        for ($i = 0; $i < 4; $i++) {
            $this->confirmChange($user, '000000')
                ->assertJsonPath('data.message', 'Неверный код');
        }

        $this->confirmChange($user, '000000')
            ->assertStatus(400)
            ->assertJsonPath('data.message', 'Слишком много неверных попыток. Запросите новый код');

        $this->assertDatabaseMissing('email_change_requests', ['user_id' => $user->getKey()]);

        // Верный код после удаления запроса уже не помогает.
        // Минутный лимит подтверждений к этому моменту исчерпан
        $this->travel(61)->seconds();

        $this->confirmChange($user, '123456')->assertStatus(404);
    }

    public function test_expired_code_is_rejected(): void
    {
        $user = User::factory()->create();
        $this->createChangeRequest($user, ['expires_at' => now()->subMinute()]);

        $this->confirmChange($user, '123456')
            ->assertStatus(400)
            ->assertJsonPath('data.message', 'Код истёк. Запросите новый');

        $this->assertNotSame(self::NEW_EMAIL, $user->fresh()->email);
        $this->assertDatabaseMissing('email_change_requests', ['user_id' => $user->getKey()]);
    }

    public function test_confirmation_without_request_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->confirmChange($user, '123456')->assertStatus(404);
    }

    public function test_code_must_be_six_digits(): void
    {
        $user = User::factory()->create();

        $this->confirmChange($user, '12ab')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code'], 'data.errors');
    }

    public function test_email_taken_between_steps_is_rejected(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $this->createChangeRequest($user);

        // Адрес зарегистрировали, пока пользователь ждал письмо
        User::factory()->create(['email' => self::NEW_EMAIL]);

        $this->confirmChange($user, '123456')->assertStatus(409);

        $this->assertNotSame(self::NEW_EMAIL, $user->fresh()->email);
        $this->assertDatabaseMissing('email_change_requests', ['user_id' => $user->getKey()]);
        Mail::assertNotQueued(EmailChangedMail::class);
    }

    public function test_request_of_other_user_is_not_used(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $this->createChangeRequest($otherUser);

        $this->confirmChange($user, '123456')->assertStatus(404);

        $this->assertNotSame(self::NEW_EMAIL, $otherUser->fresh()->email);
    }

    public function test_password_reset_code_of_old_email_is_removed(): void
    {
        $user = User::factory()->create();
        $oldEmail = $user->email;
        $this->createChangeRequest($user);

        DB::table('password_reset_tokens')->insert([
            'email' => $oldEmail,
            'token' => Hash::make('654321'),
            'created_at' => now(),
        ]);

        $this->confirmChange($user, '123456')->assertOk();

        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $oldEmail]);
    }

    public function test_login_works_with_new_email(): void
    {
        $user = User::factory()->create();
        $oldEmail = $user->email;
        $this->createChangeRequest($user);

        $this->confirmChange($user, '123456')->assertOk();

        // Сбрасываем аутентификацию после actingAs, чтобы проверить вход с нуля
        $this->app['auth']->forgetGuards();

        $this->postJson('/v1/login', [
            'email' => $oldEmail,
            'password' => 'password',
        ])->assertStatus(401);

        $this->postJson('/v1/login', [
            'email' => self::NEW_EMAIL,
            'password' => 'password',
        ])->assertOk();
    }

    public function test_guest_cannot_change_email(): void
    {
        $this->postJson('/v1/user/email', [
            'email' => self::NEW_EMAIL,
            'current_password' => 'password',
        ])->assertStatus(401);

        $this->postJson('/v1/user/email/confirm', ['code' => '123456'])
            ->assertStatus(401);
    }

    public function test_change_request_is_rate_limited(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 3; $i++) {
            $this->requestChange($user, ['current_password' => 'wrong-password'])
                ->assertStatus(422);
        }

        $this->requestChange($user, ['current_password' => 'wrong-password'])
            ->assertStatus(429);
    }

    public function test_confirmation_is_rate_limited(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->confirmChange($user, '000000')->assertStatus(404);
        }

        $this->confirmChange($user, '000000')->assertStatus(429);
    }
}
