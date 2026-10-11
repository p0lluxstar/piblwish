<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Ссылки на товар у позиции списка: поле items.*.urls.
 * Необязательны, не больше трёх, принимаются только адреса http(s);
 * отдаются и в дашборде, и на общей странице.
 */
class WishlistItemUrlTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://example.com/books/master-i-margarita';

    private const URL_2 = 'https://shop.example.org/master-i-margarita';

    private const URL_3 = 'http://books.example.net/item/42';

    private function createWishlist(User $user): Wishlist
    {
        return Wishlist::create([
            'user_id' => $user->id,
            'title' => 'День рождения',
        ]);
    }

    public function test_wishlist_is_created_with_item_urls(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/v1/wishlists', [
                'title' => 'День рождения',
                'items' => [
                    ['label' => 'Книга', 'urls' => [self::URL, self::URL_2, self::URL_3]],
                    ['label' => 'Свеча'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.items.0.urls', [self::URL, self::URL_2, self::URL_3])
            ->assertJsonPath('data.items.1.urls', []);

        $wishlist = Wishlist::firstOrFail();

        $this->assertSame([self::URL, self::URL_2, self::URL_3], $wishlist->items()->where('description', 'Книга')->first()->urls);
        $this->assertNull($wishlist->items()->where('description', 'Свеча')->first()->urls);
    }

    public function test_empty_urls_are_dropped(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/v1/wishlists', [
                'title' => 'День рождения',
                'items' => [
                    ['label' => 'Книга', 'urls' => ['', self::URL, null]],
                    ['label' => 'Свеча', 'urls' => ['', null]],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.items.0.urls', [self::URL])
            ->assertJsonPath('data.items.1.urls', []);

        $this->assertDatabaseHas('wishlist_items', ['description' => 'Свеча', 'urls' => null]);
    }

    public function test_more_than_three_urls_are_rejected(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/v1/wishlists', [
                'title' => 'День рождения',
                'items' => [[
                    'label' => 'Книга',
                    'urls' => [self::URL, self::URL_2, self::URL_3, 'https://example.com/4'],
                ]],
            ])
            ->assertStatus(422);

        $this->assertSame(
            'Можно указать не больше трёх ссылок на товар',
            $response->json('data.errors')['items.0.urls'][0]
        );

        $this->assertDatabaseCount('wishlists', 0);
    }

    public function test_item_urls_can_be_changed_on_update(): void
    {
        $user = User::factory()->create();
        $wishlist = $this->createWishlist($user);
        $wishlist->items()->create(['description' => 'Книга', 'urls' => ['https://old.example.com']]);

        $this->actingAs($user)
            ->patchJson("/v1/wishlists/{$wishlist->id}", [
                'items' => [['label' => 'Книга', 'urls' => [self::URL, self::URL_2]]],
            ])
            ->assertOk()
            ->assertJsonPath('data.items.0.urls', [self::URL, self::URL_2]);
    }

    public function test_item_urls_can_be_removed_on_update(): void
    {
        $user = User::factory()->create();
        $wishlist = $this->createWishlist($user);
        $item = $wishlist->items()->create(['description' => 'Книга', 'urls' => [self::URL]]);

        $this->actingAs($user)
            ->patchJson("/v1/wishlists/{$wishlist->id}", [
                'items' => [['id' => $item->id, 'label' => 'Книга', 'urls' => []]],
            ])
            ->assertOk()
            ->assertJsonPath('data.items.0.urls', []);

        $this->assertNull($item->fresh()->urls);
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
                'items' => [['label' => 'Книга', 'urls' => [self::URL, $url]]],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.urls.1'], 'data.errors');

        $this->assertDatabaseCount('wishlists', 0);
    }

    public function test_invalid_url_is_rejected_on_update(): void
    {
        $user = User::factory()->create();
        $wishlist = $this->createWishlist($user);
        $wishlist->items()->create(['description' => 'Книга']);

        $response = $this->actingAs($user)
            ->patchJson("/v1/wishlists/{$wishlist->id}", [
                'items' => [['label' => 'Книга', 'urls' => ['javascript:alert(1)']]],
            ])
            ->assertStatus(422);

        $this->assertSame(
            'Некорректная ссылка на товар',
            $response->json('data.errors')['items.0.urls.0'][0]
        );

        $this->assertDatabaseHas('wishlist_items', ['description' => 'Книга', 'urls' => null]);
    }

    public function test_item_urls_are_returned_in_shared_wishlist(): void
    {
        $user = User::factory()->create();
        $wishlist = $this->createWishlist($user);
        $wishlist->items()->create(['description' => 'Книга', 'urls' => [self::URL, self::URL_2]]);

        $this->getJson("/api/v1/shared-wishlists/{$wishlist->id}")
            ->assertOk()
            ->assertJsonPath('data.items.0.urls', [self::URL, self::URL_2]);
    }
}
