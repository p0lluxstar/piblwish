<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Порядок позиций списка: задаётся порядком массива items при создании
 * и редактировании и сохраняется в колонке position.
 */
class WishlistItemPositionTest extends TestCase
{
    use RefreshDatabase;

    private function createWishlist(User $user, array $labels): string
    {
        return $this->actingAs($user)
            ->postJson('/v1/wishlists', [
                'title' => 'День рождения',
                // Без режима сюрприза владелец может отмечать позиции при редактировании
                'hideSelections' => false,
                'items' => array_map(fn($label) => ['label' => $label], $labels),
            ])
            ->assertOk()
            ->json('data.id');
    }

    private function labels(array $items): array
    {
        return array_column($items, 'label');
    }

    public function test_items_keep_order_from_create_request(): void
    {
        $user = User::factory()->create();
        $id = $this->createWishlist($user, ['Книга', 'Свеча', 'Плед']);

        $items = $this->actingAs($user)
            ->getJson('/v1/wishlists')
            ->assertOk()
            ->json('data.0.items');

        $this->assertSame(['Книга', 'Свеча', 'Плед'], $this->labels($items));
        $this->assertDatabaseHas('wishlist_items', ['wishlist_id' => $id, 'description' => 'Плед', 'position' => 2]);
    }

    public function test_reordered_items_are_returned_in_new_order(): void
    {
        $user = User::factory()->create();
        $id = $this->createWishlist($user, ['Книга', 'Свеча', 'Плед']);

        $this->actingAs($user)
            ->patchJson("/v1/wishlists/{$id}", [
                'items' => [
                    ['label' => 'Плед', 'isSelected' => true],
                    ['label' => 'Книга', 'isSelected' => false],
                    ['label' => 'Свеча', 'isSelected' => false],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.items.0.label', 'Плед')
            ->assertJsonPath('data.items.0.isSelected', true);

        $items = $this->actingAs($user)
            ->getJson('/v1/wishlists')
            ->json('data.0.items');

        $this->assertSame(['Плед', 'Книга', 'Свеча'], $this->labels($items));
    }

    public function test_shared_wishlist_returns_items_in_saved_order(): void
    {
        $user = User::factory()->create();
        $id = $this->createWishlist($user, ['Книга', 'Свеча']);

        $this->actingAs($user)
            ->patchJson("/v1/wishlists/{$id}", [
                'items' => [['label' => 'Свеча'], ['label' => 'Книга']],
            ])
            ->assertOk();

        $items = $this->getJson("/api/v1/shared-wishlists/{$id}")
            ->assertOk()
            ->json('data.items');

        $this->assertSame(['Свеча', 'Книга'], $this->labels($items));
    }
}
