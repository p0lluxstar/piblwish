<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Режим сюрприза: флаг hideSelections у списка.
 *
 * При включённом флаге владелец в /v1/wishlists не получает isSelected,
 * а гости на общей странице по-прежнему видят выбранные позиции.
 * Сохранение списка владельцем не сбрасывает выбор гостей.
 */
class WishlistHideSelectionsTest extends TestCase
{
    use RefreshDatabase;

    private function createWishlist(User $user, bool $hideSelections): Wishlist
    {
        $wishlist = Wishlist::create([
            'user_id' => $user->id,
            'title' => 'День рождения',
            'hide_selections' => $hideSelections,
        ]);

        $wishlist->items()->createMany([
            ['description' => 'Плед', 'is_selected' => true, 'position' => 0],
            ['description' => 'Книга', 'is_selected' => false, 'position' => 1],
        ]);

        return $wishlist;
    }

    public function test_wishlist_is_created_without_hidden_selections_by_default(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/v1/wishlists', [
                'title' => 'День рождения',
                'items' => [['label' => 'Книга']],
            ])
            ->assertOk()
            ->assertJsonPath('data.hideSelections', false)
            ->assertJsonPath('data.items.0.isSelected', false);

        $this->assertDatabaseHas('wishlists', [
            'id' => $response->json('data.id'),
            'hide_selections' => false,
        ]);
    }

    public function test_wishlist_is_created_with_hidden_selections(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/v1/wishlists', [
                'title' => 'День рождения',
                'hideSelections' => true,
                'items' => [['label' => 'Книга']],
            ])
            ->assertOk()
            ->assertJsonPath('data.hideSelections', true)
            ->assertJsonMissingPath('data.items.0.isSelected');
    }

    public function test_owner_does_not_see_selections_when_hidden(): void
    {
        $user = User::factory()->create();
        $this->createWishlist($user, true);

        $this->actingAs($user)
            ->getJson('/v1/wishlists')
            ->assertOk()
            ->assertJsonPath('data.0.hideSelections', true)
            ->assertJsonPath('data.0.items.0.label', 'Плед')
            ->assertJsonMissingPath('data.0.items.0.isSelected')
            ->assertJsonMissingPath('data.0.items.1.isSelected');
    }

    public function test_owner_sees_selections_when_not_hidden(): void
    {
        $user = User::factory()->create();
        $this->createWishlist($user, false);

        $this->actingAs($user)
            ->getJson('/v1/wishlists')
            ->assertOk()
            ->assertJsonPath('data.0.hideSelections', false)
            ->assertJsonPath('data.0.items.0.isSelected', true)
            ->assertJsonPath('data.0.items.1.isSelected', false);
    }

    public function test_guests_see_selections_when_hidden_from_owner(): void
    {
        $wishlist = $this->createWishlist(User::factory()->create(), true);

        $this->getJson("/api/v1/shared-wishlists/{$wishlist->id}")
            ->assertOk()
            ->assertJsonPath('data.items.0.isSelected', true)
            ->assertJsonPath('data.items.1.isSelected', false)
            ->assertJsonMissingPath('data.hideSelections');
    }

    public function test_flag_can_be_toggled_without_other_fields(): void
    {
        $user = User::factory()->create();
        $wishlist = $this->createWishlist($user, false);

        $this->actingAs($user)
            ->patchJson("/v1/wishlists/{$wishlist->id}", ['hideSelections' => true])
            ->assertOk()
            ->assertJsonPath('data.hideSelections', true)
            ->assertJsonMissingPath('data.items.0.isSelected');

        $this->assertTrue($wishlist->fresh()->hide_selections);
        $this->assertTrue($wishlist->items()->where('description', 'Плед')->value('is_selected'));
    }

    public function test_saving_items_keeps_guest_selections_when_hidden(): void
    {
        $user = User::factory()->create();
        $wishlist = $this->createWishlist($user, true);
        $ids = $wishlist->items()->pluck('id', 'description');

        // Владелец переставляет позиции, переименовывает выбранную и добавляет новую;
        // isSelected из запроса не учитывается: владелец его не видел
        $this->actingAs($user)
            ->patchJson("/v1/wishlists/{$wishlist->id}", [
                'items' => [
                    ['id' => $ids['Книга'], 'label' => 'Книга', 'isSelected' => true],
                    ['id' => $ids['Плед'], 'label' => 'Тёплый плед'],
                    ['label' => 'Свеча'],
                ],
            ])
            ->assertOk()
            ->assertJsonMissingPath('data.items.0.isSelected');

        $selections = $wishlist->items()->pluck('is_selected', 'description');

        $this->assertSame(
            ['Книга' => false, 'Тёплый плед' => true, 'Свеча' => false],
            $selections->map(fn ($value) => (bool) $value)->all()
        );
    }

    public function test_ids_from_another_wishlist_do_not_transfer_selections(): void
    {
        $user = User::factory()->create();
        $wishlist = $this->createWishlist($user, true);
        $other = $this->createWishlist(User::factory()->create(), false);
        $foreignSelectedId = $other->items()->where('is_selected', true)->value('id');

        $this->actingAs($user)
            ->patchJson("/v1/wishlists/{$wishlist->id}", [
                'items' => [['id' => $foreignSelectedId, 'label' => 'Чужая позиция']],
            ])
            ->assertOk();

        $this->assertFalse((bool) $wishlist->items()->value('is_selected'));
    }

    public function test_saving_items_uses_request_selections_when_not_hidden(): void
    {
        $user = User::factory()->create();
        $wishlist = $this->createWishlist($user, false);

        $this->actingAs($user)
            ->patchJson("/v1/wishlists/{$wishlist->id}", [
                'items' => [
                    ['label' => 'Плед', 'isSelected' => false],
                    ['label' => 'Книга', 'isSelected' => true],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.items.0.isSelected', false)
            ->assertJsonPath('data.items.1.isSelected', true);
    }

    public function test_disabling_flag_with_items_keeps_guest_selections(): void
    {
        $user = User::factory()->create();
        $wishlist = $this->createWishlist($user, true);
        $ids = $wishlist->items()->pluck('id', 'description');

        // Форма владельца собрана, пока выбор был скрыт, поэтому isSelected в ней нет
        $this->actingAs($user)
            ->patchJson("/v1/wishlists/{$wishlist->id}", [
                'hideSelections' => false,
                'items' => [
                    ['id' => $ids['Плед'], 'label' => 'Плед'],
                    ['id' => $ids['Книга'], 'label' => 'Книга'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.hideSelections', false)
            ->assertJsonPath('data.items.0.isSelected', true)
            ->assertJsonPath('data.items.1.isSelected', false);
    }

    public function test_invalid_flag_is_rejected(): void
    {
        $user = User::factory()->create();
        $wishlist = $this->createWishlist($user, false);

        $this->actingAs($user)
            ->patchJson("/v1/wishlists/{$wishlist->id}", ['hideSelections' => 'maybe'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['hideSelections'], 'data.errors');
    }
}
