<?php

namespace Tests\Feature;

use App\Enums\WishlistColor;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Цвет фона списка: поле color в /v1/wishlists.
 * На странице общего списка цвет не используется, и API его не отдаёт.
 *
 * В БД и в API хранится ключ цвета из App\Enums\WishlistColor,
 * по умолчанию white.
 */
class WishlistColorTest extends TestCase
{
    use RefreshDatabase;

    private function createWishlist(User $user, WishlistColor $color = WishlistColor::White): Wishlist
    {
        return Wishlist::create([
            'user_id' => $user->id,
            'title' => 'День рождения',
            'color' => $color,
        ]);
    }

    public function test_wishlist_without_color_is_white(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/v1/wishlists', [
                'title' => 'День рождения',
                'items' => [['label' => 'Книга']],
            ])
            ->assertOk()
            ->assertJsonPath('data.color', 'white');

        $this->assertDatabaseHas('wishlists', [
            'id' => $response->json('data.id'),
            'color' => 'white',
        ]);
    }

    public function test_wishlist_is_created_with_color(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/v1/wishlists', [
                'title' => 'День рождения',
                'color' => 'mint',
                'items' => [['label' => 'Книга']],
            ])
            ->assertOk()
            ->assertJsonPath('data.color', 'mint');

        $this->assertDatabaseHas('wishlists', [
            'id' => $response->json('data.id'),
            'color' => 'mint',
        ]);
    }

    public function test_color_can_be_changed_without_other_fields(): void
    {
        $user = User::factory()->create();
        $wishlist = $this->createWishlist($user);
        $wishlist->items()->create(['description' => 'Книга']);

        $this->actingAs($user)
            ->patchJson("/v1/wishlists/{$wishlist->id}", ['color' => 'sky'])
            ->assertOk()
            ->assertJsonPath('data.color', 'sky')
            ->assertJsonPath('data.title', 'День рождения')
            ->assertJsonCount(1, 'data.items');

        $this->assertSame(WishlistColor::Sky, $wishlist->fresh()->color);
    }

    public function test_unknown_color_is_rejected_on_create(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/v1/wishlists', [
                'title' => 'День рождения',
                'color' => 'red',
                'items' => [['label' => 'Книга']],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['color'], 'data.errors')
            ->assertJsonPath('data.errors.color.0', 'Недопустимый цвет списка');

        $this->assertDatabaseCount('wishlists', 0);
    }

    public function test_unknown_color_is_rejected_on_update(): void
    {
        $user = User::factory()->create();
        $wishlist = $this->createWishlist($user, WishlistColor::Pink);

        $this->actingAs($user)
            ->patchJson("/v1/wishlists/{$wishlist->id}", ['color' => 'red'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['color'], 'data.errors');

        $this->assertSame(WishlistColor::Pink, $wishlist->fresh()->color);
    }

    public function test_user_cannot_change_color_of_another_users_wishlist(): void
    {
        $owner = User::factory()->create();
        $wishlist = $this->createWishlist($owner, WishlistColor::Pink);

        $this->actingAs(User::factory()->create())
            ->patchJson("/v1/wishlists/{$wishlist->id}", ['color' => 'sky'])
            ->assertNotFound();

        $this->assertSame(WishlistColor::Pink, $wishlist->fresh()->color);
    }

    public function test_color_is_returned_in_user_wishlists(): void
    {
        $user = User::factory()->create();
        $this->createWishlist($user, WishlistColor::Lemon);

        $this->actingAs($user)
            ->getJson('/v1/wishlists')
            ->assertOk()
            ->assertJsonPath('data.0.color', 'lemon');
    }
}
