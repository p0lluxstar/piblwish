<?php

namespace Tests\Feature;

use App\Enums\WishlistItemPriority;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Приоритет позиции списка: поле items.*.priority (1–3 или null).
 * Отдаётся в дашборде и на общей странице, в том числе в режиме сюрприза.
 */
class WishlistItemPriorityTest extends TestCase
{
    use RefreshDatabase;

    private function createWishlist(User $user, array $attributes = []): Wishlist
    {
        return Wishlist::create([
            'user_id' => $user->id,
            'title' => 'День рождения',
        ] + $attributes);
    }

    public function test_wishlist_is_created_with_item_priority(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/v1/wishlists', [
                'title' => 'День рождения',
                'items' => [
                    ['label' => 'Книга', 'priority' => 3],
                    ['label' => 'Свеча'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.items.0.priority', 3)
            ->assertJsonPath('data.items.1.priority', null);

        $this->assertDatabaseHas('wishlist_items', ['description' => 'Книга', 'priority' => 3]);
        $this->assertDatabaseHas('wishlist_items', ['description' => 'Свеча', 'priority' => null]);
    }

    public function test_item_priority_can_be_changed_and_cleared_on_update(): void
    {
        $user = User::factory()->create();
        $wishlist = $this->createWishlist($user);
        $book = $wishlist->items()->create(['description' => 'Книга', 'priority' => WishlistItemPriority::Low]);
        $candle = $wishlist->items()->create(['description' => 'Свеча', 'priority' => WishlistItemPriority::High]);

        $this->actingAs($user)
            ->patchJson("/v1/wishlists/{$wishlist->id}", [
                'items' => [
                    ['id' => $book->id, 'label' => 'Книга', 'priority' => 2],
                    ['id' => $candle->id, 'label' => 'Свеча', 'priority' => null],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.items.0.priority', 2)
            ->assertJsonPath('data.items.1.priority', null);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidPriorities(): array
    {
        return [
            'ноль' => [0],
            'больше трёх' => [4],
            'строка' => ['high'],
        ];
    }

    #[DataProvider('invalidPriorities')]
    public function test_invalid_priority_is_rejected(mixed $priority): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/v1/wishlists', [
                'title' => 'День рождения',
                'items' => [['label' => 'Книга', 'priority' => $priority]],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.priority'], 'data.errors');

        $this->assertDatabaseCount('wishlists', 0);
    }

    public function test_item_priority_is_returned_in_shared_wishlist(): void
    {
        $user = User::factory()->create();
        $wishlist = $this->createWishlist($user);
        $wishlist->items()->create(['description' => 'Книга', 'priority' => WishlistItemPriority::High]);

        $this->getJson("/api/v1/shared-wishlists/{$wishlist->id}")
            ->assertOk()
            ->assertJsonPath('data.items.0.priority', 3);
    }

    public function test_item_priority_is_visible_to_owner_in_surprise_mode(): void
    {
        $user = User::factory()->create();
        $wishlist = $this->createWishlist($user, ['hide_selections' => true]);
        $wishlist->items()->create(['description' => 'Книга', 'priority' => WishlistItemPriority::Medium]);

        $this->actingAs($user)
            ->getJson('/v1/wishlists')
            ->assertOk()
            ->assertJsonPath('data.0.items.0.priority', 2)
            ->assertJsonMissingPath('data.0.items.0.isSelected');
    }
}
