<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Стоимость позиции списка: поле items.*.price (целые рубли 0–10 000 000 или null).
 * Отдаётся в дашборде и на общей странице, в том числе в режиме сюрприза.
 */
class WishlistItemPriceTest extends TestCase
{
    use RefreshDatabase;

    private function createWishlist(User $user, array $attributes = []): Wishlist
    {
        return Wishlist::create([
            'user_id' => $user->id,
            'title' => 'День рождения',
        ] + $attributes);
    }

    public function test_wishlist_is_created_with_item_price(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/v1/wishlists', [
                'title' => 'День рождения',
                'items' => [
                    ['label' => 'Книга', 'price' => 1500],
                    ['label' => 'Свеча'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.items.0.price', 1500)
            ->assertJsonPath('data.items.1.price', null);

        $this->assertDatabaseHas('wishlist_items', ['description' => 'Книга', 'price' => 1500]);
        $this->assertDatabaseHas('wishlist_items', ['description' => 'Свеча', 'price' => null]);
    }

    public function test_item_price_can_be_changed_and_cleared_on_update(): void
    {
        $user = User::factory()->create();
        $wishlist = $this->createWishlist($user);
        $book = $wishlist->items()->create(['description' => 'Книга', 'price' => 1500]);
        $candle = $wishlist->items()->create(['description' => 'Свеча', 'price' => 700]);

        $this->actingAs($user)
            ->patchJson("/v1/wishlists/{$wishlist->id}", [
                'items' => [
                    ['id' => $book->id, 'label' => 'Книга', 'price' => 2000],
                    ['id' => $candle->id, 'label' => 'Свеча', 'price' => null],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.items.0.price', 2000)
            ->assertJsonPath('data.items.1.price', null);

        $this->assertDatabaseHas('wishlist_items', ['id' => $book->id, 'price' => 2000]);
        $this->assertDatabaseHas('wishlist_items', ['id' => $candle->id, 'price' => null]);
    }

    /**
     * @return array<string, array{int}>
     */
    public static function boundaryPrices(): array
    {
        return [
            'ноль' => [0],
            'максимум' => [10000000],
        ];
    }

    #[DataProvider('boundaryPrices')]
    public function test_boundary_price_is_accepted(int $price): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/v1/wishlists', [
                'title' => 'День рождения',
                'items' => [['label' => 'Книга', 'price' => $price]],
            ])
            ->assertOk()
            ->assertJsonPath('data.items.0.price', $price);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidPrices(): array
    {
        return [
            'отрицательная' => [-1],
            'дробная' => [1500.5],
            'больше максимума' => [10000001],
            'строка' => ['дорого'],
        ];
    }

    #[DataProvider('invalidPrices')]
    public function test_invalid_price_is_rejected_on_create(mixed $price): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/v1/wishlists', [
                'title' => 'День рождения',
                'items' => [['label' => 'Книга', 'price' => $price]],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.price'], 'data.errors');

        $this->assertDatabaseCount('wishlists', 0);
    }

    #[DataProvider('invalidPrices')]
    public function test_invalid_price_is_rejected_on_update(mixed $price): void
    {
        $user = User::factory()->create();
        $wishlist = $this->createWishlist($user);
        $book = $wishlist->items()->create(['description' => 'Книга', 'price' => 1500]);

        $this->actingAs($user)
            ->patchJson("/v1/wishlists/{$wishlist->id}", [
                'items' => [['id' => $book->id, 'label' => 'Книга', 'price' => $price]],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.price'], 'data.errors');

        $this->assertDatabaseHas('wishlist_items', ['id' => $book->id, 'price' => 1500]);
    }

    public function test_item_price_is_returned_in_shared_wishlist(): void
    {
        $user = User::factory()->create();
        $wishlist = $this->createWishlist($user);
        $wishlist->items()->create(['description' => 'Книга', 'price' => 1500]);

        $this->getJson("/api/v1/shared-wishlists/{$wishlist->id}")
            ->assertOk()
            ->assertJsonPath('data.items.0.price', 1500);
    }

    public function test_item_price_is_visible_to_owner_in_surprise_mode(): void
    {
        $user = User::factory()->create();
        $wishlist = $this->createWishlist($user, ['hide_selections' => true]);
        $wishlist->items()->create(['description' => 'Книга', 'price' => 1500]);

        $this->actingAs($user)
            ->getJson('/v1/wishlists')
            ->assertOk()
            ->assertJsonPath('data.0.items.0.price', 1500)
            ->assertJsonMissingPath('data.0.items.0.isSelected');
    }
}
