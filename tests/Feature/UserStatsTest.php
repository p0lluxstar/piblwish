<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Статистика пользователя: поле stats в /v1/user.
 *
 * Счётчики созданных карточек по типам увеличиваются при создании списка
 * (в том числе дубликата, который создаётся тем же запросом), не уменьшаются
 * при удалении списка и обнуляются при удалении аккаунта.
 */
class UserStatsTest extends TestCase
{
    use RefreshDatabase;

    private const TBANK_URL = 'https://www.tbank.ru/cf/AbCdEf123';

    public function test_new_user_has_zero_stats(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/v1/user')
            ->assertOk()
            ->assertJsonPath('data.stats', ['gift' => 0, 'todo' => 0, 'note' => 0, 'fund' => 0]);
    }

    public function test_created_wishlists_are_counted_by_type(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $this->postJson('/v1/wishlists', ['title' => 'День рождения', 'items' => [['label' => 'Книга']]])->assertOk();
        $this->postJson('/v1/wishlists', ['type' => 'gift', 'title' => 'Новый год', 'items' => [['label' => 'Шарф']]])->assertOk();
        $this->postJson('/v1/wishlists', ['type' => 'todo', 'title' => 'Дела', 'items' => [['label' => 'Позвонить']]])->assertOk();
        $this->postJson('/v1/wishlists', ['type' => 'note', 'content' => 'Текст'])->assertOk();
        $this->postJson('/v1/wishlists', [
            'type' => 'fund',
            'title' => 'Сбор',
            'items' => [['label' => 'На дом', 'urls' => [self::TBANK_URL]]],
        ])->assertOk();

        $this->getJson('/v1/user')
            ->assertJsonPath('data.stats', ['gift' => 2, 'todo' => 1, 'note' => 1, 'fund' => 1]);
    }

    public function test_rejected_wishlist_is_not_counted(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/v1/wishlists', ['title' => 'День рождения', 'color' => 'red', 'items' => [['label' => 'Книга']]])
            ->assertUnprocessable();

        $this->assertSame(0, $user->fresh()->created_gift_count);
    }

    public function test_deleted_wishlist_stays_counted(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $id = $this->postJson('/v1/wishlists', ['type' => 'note', 'content' => 'Текст'])
            ->assertOk()
            ->json('data.id');

        $this->deleteJson("/v1/wishlists/{$id}")->assertOk();

        $this->assertDatabaseMissing('wishlists', ['id' => $id]);

        $this->getJson('/v1/user')
            ->assertJsonPath('data.stats.note', 1);
    }

    public function test_stats_are_reset_when_account_is_deleted(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $this->postJson('/v1/wishlists', ['type' => 'note', 'content' => 'Текст'])->assertOk();
        $this->postJson('/v1/wishlists', ['type' => 'todo', 'title' => 'Дела', 'items' => [['label' => 'Позвонить']]])->assertOk();

        $this->deleteJson('/v1/user')->assertOk();

        $user->refresh();

        $this->assertSame(0, $user->created_note_count);
        $this->assertSame(0, $user->created_todo_count);
        $this->assertNotNull($user->deactivated_at);
    }
}
