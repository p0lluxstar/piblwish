<?php

namespace Tests\Feature;

use App\Enums\WishlistType;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Смена общей ссылки списка: POST /v1/wishlists/{id}/share-token.
 *
 * Список открывается гостям по токену ссылки (shareToken), который до первой
 * смены совпадает с id. После смены прежний адрес отвечает 404 на общей странице
 * и во всех публичных эндпоинтах, закладки, добавленные по нему, недоступны,
 * а выбор гостей и брони сохраняются.
 */
class WishlistShareLinkTest extends TestCase
{
    use RefreshDatabase;

    private function createWishlist(User $owner, array $attributes = []): Wishlist
    {
        $wishlist = Wishlist::create([
            'user_id' => $owner->id,
            'title' => 'День рождения',
            ...$attributes,
        ]);

        $wishlist->items()->create(['description' => 'Книга', 'position' => 0]);

        return $wishlist;
    }

    public function test_new_wishlist_share_token_equals_id(): void
    {
        $owner = User::factory()->create();
        $wishlist = $this->createWishlist($owner);

        $this->assertSame($wishlist->id, $wishlist->share_token);

        $this->actingAs($owner)
            ->getJson('/v1/wishlists')
            ->assertOk()
            ->assertJsonPath('data.0.shareToken', $wishlist->id);
    }

    public function test_owner_changes_link_and_old_link_stops_working(): void
    {
        $owner = User::factory()->create();
        $wishlist = $this->createWishlist($owner);
        $oldToken = $wishlist->share_token;

        $response = $this->actingAs($owner)
            ->postJson("/v1/wishlists/{$wishlist->id}/share-token")
            ->assertOk()
            ->assertJsonPath('data.id', $wishlist->id);

        $newToken = $response->json('data.shareToken');

        $this->assertNotSame($oldToken, $newToken);
        $this->assertSame($newToken, $wishlist->fresh()->share_token);

        $this->getJson("/api/v1/shared-wishlists/{$oldToken}")->assertNotFound();
        $this->patchJson("/api/v1/shared-wishlists/{$oldToken}/items", [
            'item_ids' => [$wishlist->items()->first()->id],
        ])->assertNotFound();

        // По новой ссылке гость видит токен, а не id списка
        $this->getJson("/api/v1/shared-wishlists/{$newToken}")
            ->assertOk()
            ->assertJsonPath('data.id', $newToken);
    }

    public function test_guest_selections_survive_link_change(): void
    {
        $owner = User::factory()->create();
        $wishlist = $this->createWishlist($owner);
        $itemId = $wishlist->items()->first()->id;

        $reservationToken = $this->patchJson("/api/v1/shared-wishlists/{$wishlist->share_token}/items", [
            'item_ids' => [$itemId],
        ])->assertOk()->json('data.reservation.token');

        $newToken = $this->actingAs($owner)
            ->postJson("/v1/wishlists/{$wishlist->id}/share-token")
            ->json('data.shareToken');

        $this->getJson("/api/v1/shared-wishlists/{$newToken}")
            ->assertOk()
            ->assertJsonPath('data.items.0.isSelected', true);

        // Бронь привязана к списку, а не к ссылке: по новой ссылке её можно отменить
        $this->postJson("/api/v1/shared-wishlists/{$newToken}/reservations/cancel", [
            'token' => $reservationToken,
            'item_ids' => [$itemId],
        ])->assertOk()->assertJsonPath('data.items.0.isSelected', false);
    }

    public function test_saved_bookmark_becomes_unavailable_after_link_change(): void
    {
        $owner = User::factory()->create();
        $user = User::factory()->create();
        $wishlist = $this->createWishlist($owner, ['due_date' => '2026-12-31']);

        $this->actingAs($user)->postJson("/v1/saved-wishlists/{$wishlist->share_token}")->assertOk();

        $newToken = $this->actingAs($owner)
            ->postJson("/v1/wishlists/{$wishlist->id}/share-token")
            ->json('data.shareToken');

        $this->actingAs($user)
            ->getJson('/v1/saved-wishlists')
            ->assertOk()
            ->assertJsonPath('data.0.available', false)
            ->assertJsonPath('data.0.title', null);

        // Добавление по новой ссылке возвращает закладку, а не создаёт вторую
        $this->actingAs($user)
            ->postJson("/v1/saved-wishlists/{$newToken}")
            ->assertOk()
            ->assertJsonPath('data.id', $newToken)
            ->assertJsonPath('data.available', true);

        $this->actingAs($user)
            ->getJson('/v1/saved-wishlists')
            ->assertJsonCount(1, 'data');

        $this->actingAs($user)->deleteJson("/v1/saved-wishlists/{$newToken}")->assertOk();
        $this->assertSame(0, $user->savedWishlists()->count());
    }

    public function test_shared_page_meta_uses_token(): void
    {
        $owner = User::factory()->create();
        $wishlist = $this->createWishlist($owner, ['title' => 'Новоселье']);
        $oldToken = $wishlist->share_token;

        $newToken = $this->actingAs($owner)
            ->postJson("/v1/wishlists/{$wishlist->id}/share-token")
            ->json('data.shareToken');

        $this->get("/shared-wishlist/{$newToken}")->assertOk()->assertSee('Новоселье');
        $this->get("/shared-wishlist/{$oldToken}")->assertNotFound()->assertDontSee('Новоселье');
    }

    public function test_cannot_change_link_of_foreign_archived_or_note(): void
    {
        $owner = User::factory()->create();
        $wishlist = $this->createWishlist($owner);

        $this->actingAs(User::factory()->create())
            ->postJson("/v1/wishlists/{$wishlist->id}/share-token")
            ->assertNotFound();

        $archived = $this->createWishlist($owner, ['archived_at' => now()]);

        $this->actingAs($owner)
            ->postJson("/v1/wishlists/{$archived->id}/share-token")
            ->assertStatus(409);

        $note = Wishlist::create([
            'user_id' => $owner->id,
            'type' => WishlistType::Note,
            'content' => 'Текст',
        ]);

        $this->actingAs($owner)
            ->postJson("/v1/wishlists/{$note->id}/share-token")
            ->assertUnprocessable();

        $this->assertSame($wishlist->id, $wishlist->fresh()->share_token);
    }
}
