<?php

namespace Tests\Feature;

use App\Enums\AppBackground;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Сброс настроек: POST /v1/user/settings/reset.
 *
 * Настройки возвращаются к значениям нового пользователя, остальные
 * данные аккаунта не меняются.
 */
class UserSettingsResetTest extends TestCase
{
    use RefreshDatabase;

    private const SETTINGS = ['background', 'showFriendsEvents', 'emailReminders', 'reminderDays'];

    public function test_settings_are_reset_to_new_user_defaults(): void
    {
        // Значения нового пользователя берутся из БД (fresh() перечитывает
        // значения колонок по умолчанию), поэтому тест заметит, если значение
        // по умолчанию изменят в миграции, но не в сервисе
        $defaults = $this->actingAs(User::factory()->create()->fresh())
            ->getJson('/v1/user')
            ->json('data');

        $user = User::factory()->create([
            'background' => AppBackground::Ocean,
            'show_friends_events' => false,
            'email_reminders' => false,
            'reminder_days' => 7,
        ]);

        $response = $this->actingAs($user)
            ->postJson('/v1/user/settings/reset')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email);

        foreach (self::SETTINGS as $setting) {
            $response->assertJsonPath("data.{$setting}", $defaults[$setting]);
        }

        $fresh = $user->fresh();
        $this->assertSame(AppBackground::Blossom, $fresh->background);
        $this->assertTrue($fresh->show_friends_events);
        $this->assertTrue($fresh->email_reminders);
        $this->assertSame(3, $fresh->reminder_days);
    }

    public function test_reset_keeps_account_data(): void
    {
        $user = User::factory()->create([
            'background' => AppBackground::Mint,
            'avatar_path' => 'avatars/test/photo.webp',
        ]);

        $this->actingAs($user)
            ->postJson('/v1/user/settings/reset')
            ->assertOk();

        $fresh = $user->fresh();
        $this->assertSame($user->username, $fresh->username);
        $this->assertSame($user->email, $fresh->email);
        $this->assertSame($user->password, $fresh->password);
        $this->assertSame('avatars/test/photo.webp', $fresh->avatar_path);
    }

    public function test_guest_cannot_reset_settings(): void
    {
        $this->postJson('/v1/user/settings/reset')
            ->assertUnauthorized();
    }
}
