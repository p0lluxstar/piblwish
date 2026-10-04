<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Дата создания списка (createdAt) в /v1/wishlists.
 * Правки списка её не меняют. На странице общего списка дата не отдаётся.
 */
class WishlistCreatedAtTest extends TestCase
{
    use RefreshDatabase;

    private function createWishlist(User $user): Wishlist
    {
        $wishlist = Wishlist::create([
            'user_id' => $user->id,
            'title' => 'День рождения',
        ]);
        $wishlist->items()->create(['description' => 'Книга']);

        return $wishlist;
    }

    public function test_created_at_is_returned_in_user_wishlists(): void
    {
        $user = User::factory()->create();
        $wishlist = $this->createWishlist($user);

        $this->actingAs($user)
            ->getJson('/v1/wishlists')
            ->assertOk()
            ->assertJsonPath('data.0.createdAt', $wishlist->created_at->toIso8601String());
    }

    public function test_created_at_does_not_change_when_wishlist_is_updated(): void
    {
        $user = User::factory()->create();
        $wishlist = $this->createWishlist($user);
        $original = $wishlist->created_at->toIso8601String();
        $this->travel(1)->days();

        $this->actingAs($user)
            ->patchJson("/v1/wishlists/{$wishlist->id}", [
                'title' => 'Новый год',
                'items' => [['label' => 'Свеча']],
            ])
            ->assertOk()
            ->assertJsonPath('data.createdAt', $original);
    }

    public function test_created_at_is_not_returned_in_shared_wishlist(): void
    {
        $user = User::factory()->create();
        $wishlist = $this->createWishlist($user);

        $this->getJson("/api/v1/shared-wishlists/{$wishlist->id}")
            ->assertOk()
            ->assertJsonMissingPath('data.createdAt');
    }
}
