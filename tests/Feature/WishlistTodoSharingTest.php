<?php

namespace Tests\Feature;

use App\Enums\WishlistType;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Доступ к списку дел по ссылке: флаг isShared.
 *
 * Список дел с включённым доступом открывается на общей странице только для
 * просмотра: гость видит выполненные дела, но не изменяет их. Флаг меняется
 * только у списка дел: список желаний доступен по ссылке всегда, заметка — никогда.
 */
class WishlistTodoSharingTest extends TestCase
{
    use RefreshDatabase;

    private function createTodoList(User $user, bool $isShared): Wishlist
    {
        $wishlist = Wishlist::create([
            'user_id' => $user->id,
            'type' => WishlistType::Todo,
            'title' => 'Покупки',
            'is_shared' => $isShared,
        ]);

        $wishlist->items()->create(['description' => 'Хлеб', 'position' => 0, 'is_selected' => true]);
        $wishlist->items()->create(['description' => 'Молоко', 'position' => 1]);

        return $wishlist;
    }

    public function test_todo_list_is_created_private_by_default(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/v1/wishlists', [
                'type' => 'todo',
                'title' => 'Покупки',
                'items' => [['label' => 'Хлеб']],
            ])
            ->assertOk()
            ->assertJsonPath('data.isShared', false);

        $this->assertDatabaseHas('wishlists', [
            'id' => $response->json('data.id'),
            'is_shared' => false,
        ]);
    }

    public function test_todo_list_is_created_shared(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/v1/wishlists', [
                'type' => 'todo',
                'title' => 'Покупки',
                'isShared' => true,
                'items' => [['label' => 'Хлеб']],
            ])
            ->assertOk()
            ->assertJsonPath('data.isShared', true);
    }

    public function test_is_shared_is_rejected_for_gift_and_note(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/v1/wishlists', [
                'title' => 'День рождения',
                'isShared' => true,
                'items' => [['label' => 'Книга']],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('isShared', 'data.errors');

        $this->actingAs($user)
            ->postJson('/v1/wishlists', [
                'type' => 'note',
                'content' => 'Код домофона',
                'isShared' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('isShared', 'data.errors');
    }

    public function test_gift_list_is_always_shared_and_note_never(): void
    {
        $user = User::factory()->create();

        $gift = Wishlist::create(['user_id' => $user->id, 'title' => 'День рождения']);
        $note = Wishlist::create(['user_id' => $user->id, 'type' => WishlistType::Note, 'content' => 'Текст']);

        // Флаг в запросе на изменение у них не учитывается
        $this->actingAs($user)
            ->patchJson("/v1/wishlists/{$gift->id}", ['isShared' => false])
            ->assertOk()
            ->assertJsonPath('data.isShared', true);

        $this->actingAs($user)
            ->patchJson("/v1/wishlists/{$note->id}", ['isShared' => true])
            ->assertOk()
            ->assertJsonPath('data.isShared', false);

        $this->assertFalse($note->fresh()->is_shared);
    }

    public function test_owner_toggles_sharing_of_todo_list(): void
    {
        $user = User::factory()->create();
        $wishlist = $this->createTodoList($user, false);

        $this->actingAs($user)
            ->patchJson("/v1/wishlists/{$wishlist->id}", ['isShared' => true])
            ->assertOk()
            ->assertJsonPath('data.isShared', true);

        $this->getJson("/api/v1/shared-wishlists/{$wishlist->id}")->assertOk();

        $this->actingAs($user)
            ->patchJson("/v1/wishlists/{$wishlist->id}", ['isShared' => false])
            ->assertOk()
            ->assertJsonPath('data.isShared', false);

        $this->getJson("/api/v1/shared-wishlists/{$wishlist->id}")->assertNotFound();
    }

    public function test_shared_todo_list_is_shown_to_guest_with_done_items(): void
    {
        $wishlist = $this->createTodoList(User::factory()->create(), true);

        $response = $this->getJson("/api/v1/shared-wishlists/{$wishlist->id}")
            ->assertOk()
            ->assertJsonPath('data.type', 'todo')
            ->assertJsonPath('data.title', 'Покупки')
            ->assertJsonPath('data.items.0.label', 'Хлеб')
            ->assertJsonPath('data.items.0.isSelected', true)
            ->assertJsonPath('data.items.1.isSelected', false);

        // Совместных подарков у дел нет
        $this->assertArrayNotHasKey('jointGift', $response->json('data.items.0'));
    }

    public function test_guest_cannot_change_shared_todo_list(): void
    {
        $wishlist = $this->createTodoList(User::factory()->create(), true);
        $item = $wishlist->items()->where('is_selected', false)->first();

        $this->patchJson("/api/v1/shared-wishlists/{$wishlist->id}/items", [
            'item_ids' => [$item->id],
        ])->assertNotFound();

        $this->postJson("/api/v1/shared-wishlists/{$wishlist->id}/reservations/lookup", [
            'tokens' => [Str::random(40)],
        ])->assertNotFound();

        $this->postJson("/api/v1/shared-wishlists/{$wishlist->id}/reservations/cancel", [
            'token' => Str::random(40),
            'item_ids' => [$item->id],
        ])->assertNotFound();

        $this->putJson("/api/v1/shared-wishlists/{$wishlist->id}/items/{$item->id}/joint-gift", [
            'token' => Str::random(40),
            'joint_gift' => null,
        ])->assertNotFound();

        $this->assertFalse($item->fresh()->is_selected);
        $this->assertDatabaseCount('wishlist_reservations', 0);
    }

    public function test_shared_gift_response_contains_type(): void
    {
        $wishlist = Wishlist::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'День рождения',
        ]);

        $this->getJson("/api/v1/shared-wishlists/{$wishlist->id}")
            ->assertOk()
            ->assertJsonPath('data.type', 'gift');
    }
}
