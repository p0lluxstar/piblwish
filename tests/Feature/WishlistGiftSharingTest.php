<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Доступ к списку желаний по ссылке: флаг isShared.
 *
 * Список желаний по умолчанию открыт по ссылке. Владелец может скрыть его
 * от гостей: тогда общая страница и все публичные действия отвечают 404,
 * а выбор гостей сохраняется и возвращается при повторном открытии.
 */
class WishlistGiftSharingTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Wishlist $wishlist;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->wishlist = Wishlist::create([
            'user_id' => $this->owner->id,
            'title' => 'День рождения',
        ]);

        $this->wishlist->items()->createMany([
            ['description' => 'Плед', 'position' => 0],
            ['description' => 'Книга', 'position' => 1],
        ]);
    }

    private function itemId(string $description): string
    {
        return $this->wishlist->items()->where('description', $description)->value('id');
    }

    private function setShared(bool $isShared): void
    {
        $this->actingAs($this->owner)
            ->patchJson("/v1/wishlists/{$this->wishlist->id}", ['isShared' => $isShared])
            ->assertOk()
            ->assertJsonPath('data.isShared', $isShared);
    }

    public function test_gift_list_is_shared_by_default(): void
    {
        $this->assertTrue($this->wishlist->is_shared);

        $this->actingAs($this->owner)
            ->postJson('/v1/wishlists', [
                'title' => 'Новоселье',
                'items' => [['label' => 'Ваза']],
            ])
            ->assertOk()
            ->assertJsonPath('data.isShared', true);
    }

    public function test_gift_list_can_be_created_hidden(): void
    {
        $id = $this->actingAs($this->owner)
            ->postJson('/v1/wishlists', [
                'title' => 'Новоселье',
                'isShared' => false,
                'items' => [['label' => 'Ваза']],
            ])
            ->assertOk()
            ->assertJsonPath('data.isShared', false)
            ->json('data.id');

        $this->getJson("/api/v1/shared-wishlists/{$id}")->assertNotFound();
    }

    public function test_hidden_gift_list_is_not_available_to_guests(): void
    {
        $token = $this->patchJson("/api/v1/shared-wishlists/{$this->wishlist->id}/items", [
            'item_ids' => [$this->itemId('Плед')],
        ])->assertOk()->json('data.reservation.token');

        $this->setShared(false);

        $this->getJson("/api/v1/shared-wishlists/{$this->wishlist->id}")->assertNotFound();

        $this->patchJson("/api/v1/shared-wishlists/{$this->wishlist->id}/items", [
            'item_ids' => [$this->itemId('Книга')],
        ])->assertNotFound();

        $this->postJson("/api/v1/shared-wishlists/{$this->wishlist->id}/reservations/lookup", [
            'tokens' => [$token],
        ])->assertNotFound();

        $this->postJson("/api/v1/shared-wishlists/{$this->wishlist->id}/reservations/cancel", [
            'token' => $token,
            'item_ids' => [$this->itemId('Плед')],
        ])->assertNotFound();

        $this->putJson("/api/v1/shared-wishlists/{$this->wishlist->id}/items/{$this->itemId('Плед')}/joint-gift", [
            'token' => $token,
            'joint_gift' => ['name' => 'Аня'],
        ])->assertNotFound();
    }

    public function test_selection_is_kept_while_list_is_hidden(): void
    {
        $token = $this->patchJson("/api/v1/shared-wishlists/{$this->wishlist->id}/items", [
            'item_ids' => [$this->itemId('Плед')],
        ])->assertOk()->json('data.reservation.token');

        $this->setShared(false);
        $this->setShared(true);

        $this->getJson("/api/v1/shared-wishlists/{$this->wishlist->id}")
            ->assertOk()
            ->assertJsonPath('data.items.0.isSelected', true);

        $this->postJson("/api/v1/shared-wishlists/{$this->wishlist->id}/reservations/lookup", [
            'tokens' => [$token],
        ])
            ->assertOk()
            ->assertJsonPath('data.0.itemIds', [$this->itemId('Плед')]);
    }

    public function test_hidden_gift_list_cannot_be_saved_and_bookmark_becomes_unavailable(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson("/v1/saved-wishlists/{$this->wishlist->id}")->assertOk();

        $this->setShared(false);

        $this->actingAs($user)
            ->getJson('/v1/saved-wishlists')
            ->assertOk()
            ->assertJsonPath('data.0.available', false)
            ->assertJsonPath('data.0.title', null);

        $other = User::factory()->create();

        $this->actingAs($other)->postJson("/v1/saved-wishlists/{$this->wishlist->id}")->assertNotFound();
    }
}
