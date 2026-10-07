<?php

namespace Tests\Feature;

use App\Enums\AppBackground;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Фон приложения: поле background в /v1/user.
 *
 * В БД и в API хранится ключ фона из App\Enums\AppBackground,
 * по умолчанию blossom (прежний фон).
 */
class UserBackgroundTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_user_has_default_background(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/v1/user')
            ->assertOk()
            ->assertJsonPath('data.background', 'blossom');
    }

    public function test_background_is_changed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patchJson('/v1/user', ['background' => 'ocean'])
            ->assertOk()
            ->assertJsonPath('data.background', 'ocean')
            ->assertJsonPath('data.email', $user->email);

        $this->assertSame(AppBackground::Ocean, $user->fresh()->background);

        $this->actingAs($user)
            ->getJson('/v1/user')
            ->assertJsonPath('data.background', 'ocean');
    }

    public function test_unknown_background_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patchJson('/v1/user', ['background' => 'neon'])
            ->assertUnprocessable()
            ->assertJsonPath('data.errors.background.0', 'Недопустимый фон');

        $this->assertSame(AppBackground::Blossom, $user->fresh()->background);
    }

    public function test_background_is_required(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patchJson('/v1/user', [])
            ->assertUnprocessable()
            ->assertJsonPath('data.errors.background.0', 'Выберите фон');
    }

    public function test_guest_cannot_change_background(): void
    {
        $this->patchJson('/v1/user', ['background' => 'ocean'])
            ->assertUnauthorized();
    }
}
