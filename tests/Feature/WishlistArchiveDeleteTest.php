<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Удаление архива: DELETE /v1/wishlists/archived.
 *
 * Удаляются только переданные списки пользователя, которые всё ещё в архиве.
 */
class WishlistArchiveDeleteTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
    }

    private function makeWishlist(?User $user = null, bool $archived = true): Wishlist
    {
        $wishlist = Wishlist::create([
            'user_id' => ($user ?? $this->owner)->id,
            'title' => 'Список',
            'archived_at' => $archived ? now() : null,
        ]);

        $wishlist->items()->create(['description' => 'Плед', 'position' => 0]);

        return $wishlist;
    }

    public function test_deletes_passed_archived_wishlists(): void
    {
        $first = $this->makeWishlist();
        $second = $this->makeWishlist();

        $this->actingAs($this->owner)
            ->deleteJson('/v1/wishlists/archived', ['ids' => [$first->id, $second->id]])
            ->assertOk()
            ->assertJsonPath('data.deletedCount', 2);

        $this->assertDatabaseMissing('wishlists', ['id' => $first->id]);
        $this->assertDatabaseMissing('wishlists', ['id' => $second->id]);
        $this->assertDatabaseMissing('wishlist_items', ['wishlist_id' => $first->id]);
    }

    public function test_skips_active_foreign_and_not_passed_wishlists(): void
    {
        $archived = $this->makeWishlist();
        $notPassed = $this->makeWishlist();
        $active = $this->makeWishlist(archived: false);
        $foreign = $this->makeWishlist(User::factory()->create());

        $this->actingAs($this->owner)
            ->deleteJson('/v1/wishlists/archived', [
                'ids' => [$archived->id, $active->id, $foreign->id, 'missing'],
            ])
            ->assertOk()
            ->assertJsonPath('data.deletedCount', 1);

        $this->assertDatabaseMissing('wishlists', ['id' => $archived->id]);
        $this->assertDatabaseHas('wishlists', ['id' => $notPassed->id]);
        $this->assertDatabaseHas('wishlists', ['id' => $active->id]);
        $this->assertDatabaseHas('wishlists', ['id' => $foreign->id]);
    }

    public function test_requires_ids(): void
    {
        $this->actingAs($this->owner)
            ->deleteJson('/v1/wishlists/archived', ['ids' => []])
            ->assertStatus(422);
    }

    public function test_requires_authentication(): void
    {
        $wishlist = $this->makeWishlist();

        $this->deleteJson('/v1/wishlists/archived', ['ids' => [$wishlist->id]])
            ->assertUnauthorized();

        $this->assertDatabaseHas('wishlists', ['id' => $wishlist->id]);
    }
}
