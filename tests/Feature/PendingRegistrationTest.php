<?php

namespace Tests\Feature;

use App\Mail\VerificationCodeMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Завершение регистрации, если код не ввели сразу: вход с верным паролем
 * отправляет новый код, а повторная регистрация на тот же email
 * перезаписывает неподтверждённый аккаунт.
 */
class PendingRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function register(array $overrides = []): TestResponse
    {
        return $this->postJson('/v1/register', [
            'username' => 'newuser',
            'email' => 'new@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            ...$overrides,
        ]);
    }

    // Код из последнего перехваченного письма
    private function lastCode(): string
    {
        return Mail::queued(VerificationCodeMail::class)->last()->code;
    }

    public function test_login_of_unverified_account_sends_new_code(): void
    {
        Mail::fake();

        $user = User::factory()->unverified()->create();

        $this->postJson('/v1/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertStatus(403);

        $this->assertGuest('web');
        Mail::assertQueued(VerificationCodeMail::class, 1);

        $this->postJson('/v1/verify-registration', [
            'email' => $user->email,
            'code' => $this->lastCode(),
        ])->assertOk();

        $this->postJson('/v1/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk();

        $this->assertAuthenticatedAs($user->fresh(), 'web');
    }

    public function test_login_with_wrong_password_does_not_send_code(): void
    {
        Mail::fake();

        $user = User::factory()->unverified()->create();

        $this->postJson('/v1/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertStatus(401);

        Mail::assertNothingQueued();
    }

    public function test_registration_overwrites_unverified_account_with_same_email(): void
    {
        Mail::fake();

        $this->register()->assertSuccessful();

        $this->register([
            'username' => 'otheruser',
            'password' => 'another-password',
            'password_confirmation' => 'another-password',
        ])->assertSuccessful();

        $this->assertSame(1, User::where('email', 'new@example.com')->count());

        $user = User::where('email', 'new@example.com')->firstOrFail();

        $this->assertSame('otheruser', $user->username);
        $this->assertTrue(Hash::check('another-password', $user->password));
        $this->assertSame(1, $user->verificationRegistrationCodes()->count());

        // Действует код из второго письма
        $this->postJson('/v1/verify-registration', [
            'email' => $user->email,
            'code' => $this->lastCode(),
        ])->assertOk();
    }

    public function test_registration_keeps_own_username_of_unverified_account(): void
    {
        Mail::fake();

        $this->register()->assertSuccessful();

        $this->register([
            'password' => 'another-password',
            'password_confirmation' => 'another-password',
        ])->assertSuccessful();

        $this->assertSame(1, User::where('username', 'newuser')->count());
    }

    public function test_username_of_another_unverified_account_is_still_taken(): void
    {
        Mail::fake();

        $this->register()->assertSuccessful();

        $this->register(['email' => 'second@example.com'])
            ->assertStatus(422)
            ->assertJsonPath('data.errors.username.0', 'Такой логин уже занят');
    }

    public function test_verified_and_deleted_emails_are_still_taken(): void
    {
        Mail::fake();

        $verified = User::factory()->create();
        $deleted = User::factory()->unverified()->create(['deactivated_at' => now()]);

        foreach ([$verified, $deleted] as $user) {
            $this->register(['email' => $user->email])
                ->assertStatus(422)
                ->assertJsonPath('data.errors.email.0', 'Такой email уже зарегистрирован');
        }

        Mail::assertNothingQueued();
    }
}
