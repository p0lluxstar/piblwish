<?php

namespace Tests\Feature;

use App\Mail\PasswordChangedMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Смена пароля: PUT /v1/user/password.
 *
 * Пользователь остаётся в системе, его сессии на других устройствах
 * и Sanctum-токены удаляются.
 */
class ChangePasswordTest extends TestCase
{
    use RefreshDatabase;

    private function changePassword(User $user, array $overrides = []): TestResponse
    {
        return $this->actingAs($user)->putJson('/v1/user/password', [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
            ...$overrides,
        ]);
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

    public function test_password_is_changed(): void
    {
        $user = User::factory()->create();

        $this->changePassword($user)
            ->assertOk()
            ->assertJsonPath('data.message', 'Пароль изменён');

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_other_sessions_and_tokens_are_deleted(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->createSession($user, 'user-other-device');
        $this->createSession($otherUser, 'other-user-session');
        $user->createToken('device');

        $this->changePassword($user)->assertOk();

        $this->assertDatabaseMissing('sessions', ['id' => 'user-other-device']);
        // Сессии других пользователей не затрагиваются
        $this->assertDatabaseHas('sessions', ['id' => 'other-user-session']);
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_notification_email_is_queued(): void
    {
        Mail::fake();

        $user = User::factory()->create();

        $this->changePassword($user)->assertOk();

        Mail::assertQueued(
            PasswordChangedMail::class,
            fn (PasswordChangedMail $mail): bool => $mail->hasTo($user->email)
                && $mail->username === $user->username
        );
    }

    public function test_notification_email_is_not_sent_on_failed_change(): void
    {
        Mail::fake();

        $user = User::factory()->create();

        $this->changePassword($user, ['current_password' => 'wrong-password'])
            ->assertStatus(422);

        Mail::assertNothingQueued();
    }

    public function test_notification_email_renders(): void
    {
        $mail = new PasswordChangedMail(
            username: 'john_doe',
            changedAt: '03.10.2026 12:00 (МСК)',
            ipAddress: '127.0.0.1',
        );

        $mail->assertHasSubject('Пароль от аккаунта изменён');
        $mail->assertSeeInHtml('john_doe');
        $mail->assertSeeInHtml('03.10.2026 12:00 (МСК)');
        $mail->assertSeeInHtml('127.0.0.1');
    }

    public function test_wrong_current_password_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->changePassword($user, ['current_password' => 'wrong-password'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['current_password'], 'data.errors');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_confirmation_must_match(): void
    {
        $user = User::factory()->create();

        $this->changePassword($user, ['password_confirmation' => 'another-password'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password'], 'data.errors');
    }

    public function test_new_password_must_be_at_least_eight_characters(): void
    {
        $user = User::factory()->create();

        $this->changePassword($user, [
            'password' => 'short',
            'password_confirmation' => 'short',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password'], 'data.errors');
    }

    public function test_new_password_must_differ_from_current(): void
    {
        $user = User::factory()->create();

        $this->changePassword($user, [
            'password' => 'password',
            'password_confirmation' => 'password',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password'], 'data.errors');
    }

    public function test_guest_cannot_change_password(): void
    {
        $this->putJson('/v1/user/password', [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertStatus(401);
    }

    public function test_login_works_only_with_new_password(): void
    {
        $user = User::factory()->create();

        $this->changePassword($user)->assertOk();

        // Сбрасываем аутентификацию после actingAs, чтобы проверить вход с нуля
        $this->app['auth']->forgetGuards();

        $this->postJson('/v1/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertStatus(401);

        $this->postJson('/v1/login', [
            'email' => $user->email,
            'password' => 'new-password',
        ])->assertOk();
    }

    public function test_password_change_is_rate_limited(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->changePassword($user, ['current_password' => 'wrong-password'])
                ->assertStatus(422);
        }

        $this->changePassword($user, ['current_password' => 'wrong-password'])
            ->assertStatus(429);
    }
}
