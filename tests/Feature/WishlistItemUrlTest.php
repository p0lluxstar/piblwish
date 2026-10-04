<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Ссылка на товар у позиции списка: поле items.*.url.
 * Необязательна, принимаются только адреса http(s); отдаётся и в дашборде, и на общей странице.
 */
class WishlistItemUrlTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://example.com/books/master-i-margarita';

    private function createWishlist(User $user): Wishlist
    {
        return Wishlist::create([
            'user_id' => $user->id,
            'title' => 'День рождения',
        ]);
    }

    public function test_wishlist_is_created_with_item_url(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/v1/wishlists', [
                'title' => 'День рождения',
                'items' => [
                    ['label' => 'Книга', 'url' => self::URL],
                    ['label' => 'Свеча'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.items.0.url', self::URL)
            ->assertJsonPath('data.items.1.url', null);

        $this->assertDatabaseHas('wishlist_items', ['description' => 'Книга', 'url' => self::URL]);
        $this->assertDatabaseHas('wishlist_items', ['description' => 'Свеча', 'url' => null]);
    }

    public function test_empty_url_is_saved_as_null(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/v1/wishlists', [
                'title' => 'День рождения',
                'items' => [['label' => 'Книга', 'url' => '']],
            ])
            ->assertOk()
            ->assertJsonPath('data.items.0.url', null);
    }

    public function test_item_url_can_be_changed_on_update(): void
    {
        $user = User::factory()->create();
        $wishlist = $this->createWishlist($user);
        $wishlist->items()->create(['description' => 'Книга', 'url' => 'https://old.example.com']);

        $this->actingAs($user)
            ->patchJson("/v1/wishlists/{$wishlist->id}", [
                'items' => [['label' => 'Книга', 'url' => self::URL]],
            ])
            ->assertOk()
            ->assertJsonPath('data.items.0.url', self::URL);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidUrls(): array
    {
        return [
            'javascript' => ['javascript:alert(1)'],
            'data' => ['data:text/html,<script>alert(1)</script>'],
            'ftp' => ['ftp://example.com/file'],
            'не адрес' => ['просто текст'],
            'слишком длинный' => ['https://example.com/'.str_repeat('a', 2048)],
        ];
    }

    #[DataProvider('invalidUrls')]
    public function test_invalid_url_is_rejected_on_create(string $url): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/v1/wishlists', [
                'title' => 'День рождения',
                'items' => [['label' => 'Книга', 'url' => $url]],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.url'], 'data.errors');

        $this->assertDatabaseCount('wishlists', 0);
    }

    public function test_invalid_url_is_rejected_on_update(): void
    {
        $user = User::factory()->create();
        $wishlist = $this->createWishlist($user);
        $wishlist->items()->create(['description' => 'Книга']);

        $response = $this->actingAs($user)
            ->patchJson("/v1/wishlists/{$wishlist->id}", [
                'items' => [['label' => 'Книга', 'url' => 'javascript:alert(1)']],
            ])
            ->assertStatus(422);

        $this->assertSame(
            'Некорректная ссылка на товар',
            $response->json('data.errors')['items.0.url'][0]
        );

        $this->assertDatabaseHas('wishlist_items', ['description' => 'Книга', 'url' => null]);
    }

    public function test_item_url_is_returned_in_shared_wishlist(): void
    {
        $user = User::factory()->create();
        $wishlist = $this->createWishlist($user);
        $wishlist->items()->create(['description' => 'Книга', 'url' => self::URL]);

        $this->getJson("/api/v1/shared-wishlists/{$wishlist->id}")
            ->assertOk()
            ->assertJsonPath('data.items.0.url', self::URL);
    }
}
