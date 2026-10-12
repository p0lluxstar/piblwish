<?php

namespace Tests\Feature;

use App\Mail\VerificationCodeMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Повторная отправка кода регистрации: POST /v1/verify-registration/resend.
 *
 * Новый код заменяет прежний. Ответ не должен раскрывать,
 * зарегистрирован ли email.
 */
class ResendVerificationCodeTest extends TestCase
{
    use RefreshDatabase;

    private const MESSAGE = 'Если аккаунт ожидает подтверждения, на него отправлен новый код';

    private function register(): User
    {
        Mail::fake();

        $this->postJson('/v1/register', [
            'username' => 'newuser',
            'email' => 'new@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSuccessful();

        return User::where('email', 'new@example.com')->firstOrFail();
    }

    // Возвращает код из последнего перехваченного письма
    private function lastCode(): string
    {
        return Mail::queued(VerificationCodeMail::class)->last()->code;
    }

    public function test_resend_replaces_previous_code(): void
    {
        $user = $this->register();
        $oldCode = $this->lastCode();

        $this->travel(3)->minutes();

        $this->postJson('/v1/verify-registration/resend', ['email' => $user->email])
            ->assertOk()
            ->assertJsonPath('data.message', self::MESSAGE);

        Mail::assertQueued(VerificationCodeMail::class, 2);
        $newCode = $this->lastCode();

        $this->assertSame(1, $user->verificationRegistrationCodes()->count());

        if ($oldCode !== $newCode) {
            $this->postJson('/v1/verify-registration', ['email' => $user->email, 'code' => $oldCode])
                ->assertStatus(400);
        }

        $this->postJson('/v1/verify-registration', ['email' => $user->email, 'code' => $newCode])
            ->assertOk();

        $this->assertTrue($user->fresh()->is_active);
    }

    public function test_unknown_and_verified_emails_get_same_response_without_mail(): void
    {
        Mail::fake();

        $verified = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);

        foreach (['nobody@example.com', $verified->email] as $email) {
            $this->postJson('/v1/verify-registration/resend', ['email' => $email])
                ->assertOk()
                ->assertJsonPath('data.message', self::MESSAGE);
        }

        Mail::assertNothingQueued();
    }

    public function test_resend_is_limited_to_once_per_minute_for_email(): void
    {
        $user = $this->register();

        $this->postJson('/v1/verify-registration/resend', ['email' => $user->email])->assertOk();
        $this->postJson('/v1/verify-registration/resend', ['email' => $user->email])->assertStatus(429);
    }
}
