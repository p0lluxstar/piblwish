<?php

namespace Tests\Feature;

use App\Enums\WishlistType;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Дата списка: поле dueDate в /v1/wishlists и в ответе общей страницы.
 *
 * У списка дел это срок, до которого нужно всё сделать, у списка желаний —
 * дата события. Хранится только дата (Y-m-d), без даты — null.
 * У заметки даты нет.
 */
class WishlistDueDateTest extends TestCase
{
    use RefreshDatabase;

    public function test_wishlist_without_due_date_has_null(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/v1/wishlists', [
                'title' => 'День рождения',
                'items' => [['label' => 'Книга']],
            ])
            ->assertOk()
            ->assertJsonPath('data.dueDate', null);
    }

    public function test_gift_and_todo_are_created_with_due_date(): void
    {
        $user = User::factory()->create();

        foreach (['gift', 'todo'] as $type) {
            $response = $this->actingAs($user)
                ->postJson('/v1/wishlists', [
                    'type' => $type,
                    'title' => 'Список',
                    'dueDate' => '2026-12-31',
                    'items' => [['label' => 'Позиция']],
                ])
                ->assertOk()
                ->assertJsonPath('data.dueDate', '2026-12-31');

            $this->assertSame(
                '2026-12-31',
                Wishlist::find($response->json('data.id'))->due_date->toDateString()
            );
        }
    }

    public function test_note_with_due_date_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/v1/wishlists', [
                'type' => 'note',
                'content' => 'Текст',
                'dueDate' => '2026-12-31',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('data.errors.dueDate.0', 'У заметки нет даты');
    }

    public function test_invalid_due_date_is_rejected(): void
    {
        $user = User::factory()->create();

        foreach (['31.12.2026', '2026-02-30', '1999-12-31', '2100-01-01'] as $dueDate) {
            $this->actingAs($user)
                ->postJson('/v1/wishlists', [
                    'title' => 'День рождения',
                    'dueDate' => $dueDate,
                    'items' => [['label' => 'Книга']],
                ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('dueDate', 'data.errors');
        }
    }

    public function test_due_date_can_be_changed_and_removed(): void
    {
        $user = User::factory()->create();
        $wishlist = Wishlist::create([
            'user_id' => $user->id,
            'type' => WishlistType::Todo,
            'title' => 'Переезд',
            'due_date' => '2026-11-01',
        ]);

        $this->actingAs($user)
            ->patchJson("/v1/wishlists/{$wishlist->id}", ['dueDate' => '2026-11-15'])
            ->assertOk()
            ->assertJsonPath('data.dueDate', '2026-11-15')
            ->assertJsonPath('data.title', 'Переезд');

        // Без поля dueDate дата не меняется
        $this->actingAs($user)
            ->patchJson("/v1/wishlists/{$wishlist->id}", ['color' => 'mint'])
            ->assertOk()
            ->assertJsonPath('data.dueDate', '2026-11-15');

        $this->actingAs($user)
            ->patchJson("/v1/wishlists/{$wishlist->id}", ['dueDate' => null])
            ->assertOk()
            ->assertJsonPath('data.dueDate', null);

        $this->assertNull($wishlist->fresh()->due_date);
    }

    public function test_note_ignores_due_date_on_update(): void
    {
        $user = User::factory()->create();
        $note = Wishlist::create([
            'user_id' => $user->id,
            'type' => WishlistType::Note,
            'content' => 'Текст',
        ]);

        $this->actingAs($user)
            ->patchJson("/v1/wishlists/{$note->id}", ['dueDate' => '2026-12-31'])
            ->assertOk()
            ->assertJsonPath('data.dueDate', null);

        $this->assertNull($note->fresh()->due_date);
    }

    public function test_shared_wishlist_returns_due_date(): void
    {
        $user = User::factory()->create();
        $wishlist = Wishlist::create([
            'user_id' => $user->id,
            'title' => 'День рождения',
            'due_date' => '2026-12-31',
        ]);
        $wishlist->items()->create(['description' => 'Книга']);

        $this->getJson("/api/v1/shared-wishlists/{$wishlist->id}")
            ->assertOk()
            ->assertJsonPath('data.dueDate', '2026-12-31');
    }
}
