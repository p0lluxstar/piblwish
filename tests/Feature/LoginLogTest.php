<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Запись об успешном входе в журнале (AuthService::login).
 *
 * Пишется только вход с паролем, с ID пользователя и без email.
 */
class LoginLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_login_is_logged_with_user_id(): void
    {
        $user = User::factory()->create();
        Log::spy();

        $this->postJson('/v1/login', [
            'email' => $user->email,
            'password' => 'password',
            'remember' => true,
        ])->assertOk();

        Log::shouldHaveReceived('info')
            ->with('User logged in', ['user_id' => $user->getKey(), 'remember' => true])
            ->once();
    }

    public function test_failed_and_unverified_logins_are_not_logged(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $unverified = User::factory()->create(['is_active' => false]);
        Log::spy();

        $this->postJson('/v1/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertStatus(401);

        $this->postJson('/v1/login', [
            'email' => $unverified->email,
            'password' => 'password',
        ])->assertStatus(403);

        Log::shouldNotHaveReceived('info', fn (string $message): bool => $message === 'User logged in');
    }
}
