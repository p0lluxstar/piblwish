<?php

namespace Tests\Feature;

use App\Enums\WishlistType;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Отметка дела с карточки: PATCH /v1/wishlists/{id}/items/{itemId}.
 *
 * Работает только для списков дел и только для своих списков.
 */
class WishlistItemToggleTest extends TestCase
{
    use RefreshDatabase;

    private function createList(User $user, WishlistType $type): Wishlist
    {
        $wishlist = Wishlist::create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => 'Список',
        ]);

        $wishlist->items()->create(['description' => 'Первое', 'position' => 0]);
        $wishlist->items()->create(['description' => 'Второе', 'position' => 1]);

        return $wishlist->load('items');
    }

    public function test_todo_item_is_marked_and_unmarked(): void
    {
        $user = User::factory()->create();
        $wishlist = $this->createList($user, WishlistType::Todo);
        $item = $wishlist->items[1];

        $this->actingAs($user)
            ->patchJson("/v1/wishlists/{$wishlist->id}/items/{$item->id}", ['isSelected' => true])
            ->assertOk()
            ->assertJsonPath('data.items.0.isSelected', false)
            ->assertJsonPath('data.items.1.isSelected', true);

        $this->assertDatabaseHas('wishlist_items', ['id' => $item->id, 'is_selected' => true]);

        $this->actingAs($user)
            ->patchJson("/v1/wishlists/{$wishlist->id}/items/{$item->id}", ['isSelected' => false])
            ->assertOk()
            ->assertJsonPath('data.items.1.isSelected', false);
    }

    public function test_gift_list_item_cannot_be_toggled(): void
    {
        $user = User::factory()->create();
        $wishlist = $this->createList($user, WishlistType::Gift);
        $item = $wishlist->items[0];

        $this->actingAs($user)
            ->patchJson("/v1/wishlists/{$wishlist->id}/items/{$item->id}", ['isSelected' => true])
            ->assertStatus(422);

        $this->assertDatabaseHas('wishlist_items', ['id' => $item->id, 'is_selected' => false]);
    }

    public function test_foreign_list_and_foreign_item_are_not_found(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $wishlist = $this->createList($owner, WishlistType::Todo);
        $otherList = $this->createList($owner, WishlistType::Todo);

        $this->actingAs($stranger)
            ->patchJson("/v1/wishlists/{$wishlist->id}/items/{$wishlist->items[0]->id}", ['isSelected' => true])
            ->assertNotFound();

        // Позиция другого списка того же владельца
        $this->actingAs($owner)
            ->patchJson("/v1/wishlists/{$wishlist->id}/items/{$otherList->items[0]->id}", ['isSelected' => true])
            ->assertNotFound();
    }

    public function test_is_selected_is_required(): void
    {
        $user = User::factory()->create();
        $wishlist = $this->createList($user, WishlistType::Todo);

        $this->actingAs($user)
            ->patchJson("/v1/wishlists/{$wishlist->id}/items/{$wishlist->items[0]->id}", [])
            ->assertStatus(422);
    }
}
