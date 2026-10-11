<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
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

    public function test_gift_wishlist_is_created_with_hidden_selections_by_default(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/v1/wishlists', [
                'title' => 'День рождения',
                'items' => [['label' => 'Книга']],
            ])
            ->assertOk()
            ->assertJsonPath('data.hideSelections', true)
            ->assertJsonMissingPath('data.items.0.isSelected');

        $this->assertDatabaseHas('wishlists', [
            'id' => $response->json('data.id'),
            'hide_selections' => true,
        ]);
    }

    public function test_wishlist_is_created_without_hidden_selections_when_disabled(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/v1/wishlists', [
                'title' => 'День рождения',
                'hideSelections' => false,
                'items' => [['label' => 'Книга']],
            ])
            ->assertOk()
            ->assertJsonPath('data.hideSelections', false)
            ->assertJsonPath('data.items.0.isSelected', false);
    }

    public function test_todo_list_is_created_without_hidden_selections_by_default(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/v1/wishlists', [
                'type' => 'todo',
                'title' => 'Дела',
                'items' => [['label' => 'Купить продукты']],
            ])
            ->assertOk()
            ->assertJsonPath('data.hideSelections', false);
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

    public function test_owner_gets_selected_item_ids_in_surprise_mode(): void
    {
        $user = User::factory()->create();
        $wishlist = $this->createWishlist($user, true);
        $pledId = $wishlist->items()->where('description', 'Плед')->value('id');

        // Окно редактирования показывает выбор гостей, как только владелец выключает режим
        $this->actingAs($user)
            ->getJson("/v1/wishlists/{$wishlist->id}/selections?reveal=1")
            ->assertOk()
            ->assertJsonPath('data.itemIds', [$pledId])
            ->assertJsonStructure(['data' => ['checkedAt']]);

        // Сам запрос режим не выключает
        $this->assertTrue($wishlist->fresh()->hide_selections);
    }

    public function test_selections_without_reveal_hide_item_ids_in_surprise_mode(): void
    {
        $user = User::factory()->create();
        $wishlist = $this->createWishlist($user, true);

        // При открытии окна в режиме сюрприза нужно только время проверки
        $this->actingAs($user)
            ->getJson("/v1/wishlists/{$wishlist->id}/selections")
            ->assertOk()
            ->assertJsonStructure(['data' => ['checkedAt']])
            ->assertJsonMissingPath('data.itemIds');
    }

    public function test_selections_include_item_ids_when_not_hidden(): void
    {
        $user = User::factory()->create();
        $wishlist = $this->createWishlist($user, false);
        $pledId = $wishlist->items()->where('description', 'Плед')->value('id');

        $this->actingAs($user)
            ->getJson("/v1/wishlists/{$wishlist->id}/selections")
            ->assertOk()
            ->assertJsonPath('data.itemIds', [$pledId]);
    }

    public function test_selected_item_ids_are_not_available_to_another_user(): void
    {
        $wishlist = $this->createWishlist(User::factory()->create(), true);

        $this->actingAs(User::factory()->create())
            ->getJson("/v1/wishlists/{$wishlist->id}/selections")
            ->assertNotFound();
    }

    public function test_selected_item_ids_require_authentication(): void
    {
        $wishlist = $this->createWishlist(User::factory()->create(), true);

        $this->getJson("/v1/wishlists/{$wishlist->id}/selections")
            ->assertUnauthorized();
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

    public function test_saving_items_ignores_request_selections_when_not_hidden(): void
    {
        $user = User::factory()->create();
        $wishlist = $this->createWishlist($user, false);
        $ids = $wishlist->items()->pluck('id', 'description');

        // В списке желаний отметки ставят гости: владелец снимает их отдельным запросом
        $this->actingAs($user)
            ->patchJson("/v1/wishlists/{$wishlist->id}", [
                'items' => [
                    ['id' => $ids['Плед'], 'label' => 'Плед', 'isSelected' => false],
                    ['id' => $ids['Книга'], 'label' => 'Книга', 'isSelected' => true],
                    ['label' => 'Свеча', 'isSelected' => true],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.items.0.isSelected', true)
            ->assertJsonPath('data.items.1.isSelected', false)
            ->assertJsonPath('data.items.2.isSelected', false);
    }


    // Снять выбор гостя; по умолчанию окно получило выбор после всех броней теста
    private function clearSelection(Wishlist $wishlist, string $itemId, ?string $checkedAt = null): TestResponse
    {
        return $this->deleteJson("/v1/wishlists/{$wishlist->id}/items/{$itemId}/selection", [
            'checkedAt' => $checkedAt ?? now()->addSecond()->toIso8601String(),
        ]);
    }

    public function test_owner_clears_guest_selection(): void
    {
        $user = User::factory()->create();
        $wishlist = $this->createWishlist($user, false);
        $itemId = $wishlist->items()->where('description', 'Плед')->value('id');

        $this->actingAs($user);
        $this->clearSelection($wishlist, $itemId)
            ->assertOk()
            ->assertJsonPath('data.items.0.isSelected', false);

        $this->assertFalse((bool) $wishlist->items()->where('id', $itemId)->value('is_selected'));
    }

    public function test_owner_frees_item_in_surprise_mode_without_learning_selection(): void
    {
        $user = User::factory()->create();
        $wishlist = $this->createWishlist($user, true);
        $ids = $wishlist->items()->pluck('id', 'description');

        // Ответы для выбранной и невыбранной позиции не различаются
        $this->actingAs($user);
        $selected = $this->clearSelection($wishlist, $ids['Плед'])
            ->assertOk()
            ->assertJsonMissingPath('data.items.0.isSelected');
        $unselected = $this->clearSelection($wishlist, $ids['Книга'])
            ->assertOk();

        $this->assertSame($selected->json(), $unselected->json());
        $this->assertFalse((bool) $wishlist->items()->where('id', $ids['Плед'])->value('is_selected'));
        $this->assertTrue($wishlist->fresh()->hide_selections);
    }

    public function test_selection_made_after_check_is_not_cleared(): void
    {
        $user = User::factory()->create();
        $wishlist = $this->createWishlist($user, false);
        $bookId = $wishlist->items()->where('description', 'Книга')->value('id');

        // Окно получило выбор, потом гость выбрал книгу
        $checkedAt = now()->subMinute()->toIso8601String();

        $this->patchJson("/api/v1/shared-wishlists/{$wishlist->id}/items", ['item_ids' => [$bookId]])
            ->assertOk();

        $this->actingAs($user);
        $this->clearSelection($wishlist, $bookId, $checkedAt)
            ->assertStatus(409)
            ->assertJsonPath('data.message', 'Состояние позиции изменилось после открытия окна');

        $this->assertTrue((bool) $wishlist->items()->where('id', $bookId)->value('is_selected'));
        $this->assertNotNull($wishlist->items()->where('id', $bookId)->value('reservation_id'));
    }

    public function test_selection_made_in_same_second_as_check_is_not_cleared(): void
    {
        $user = User::factory()->create();
        $wishlist = $this->createWishlist($user, false);
        $bookId = $wishlist->items()->where('description', 'Книга')->value('id');

        $this->freezeSecond();
        $this->patchJson("/api/v1/shared-wishlists/{$wishlist->id}/items", ['item_ids' => [$bookId]])
            ->assertOk();

        // created_at хранится с точностью до секунды: в той же секунде нельзя
        // определить, что было раньше, поэтому выбор гостя сохраняется
        $this->actingAs($user);
        $this->clearSelection($wishlist, $bookId, now()->toIso8601String())
            ->assertStatus(409);

        $this->assertTrue((bool) $wishlist->items()->where('id', $bookId)->value('is_selected'));
    }

    public function test_checked_at_is_required_to_clear_selection(): void
    {
        $user = User::factory()->create();
        $wishlist = $this->createWishlist($user, false);
        $itemId = $wishlist->items()->where('description', 'Плед')->value('id');

        $this->actingAs($user)
            ->deleteJson("/v1/wishlists/{$wishlist->id}/items/{$itemId}/selection")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['checkedAt'], 'data.errors');

        $this->assertTrue((bool) $wishlist->items()->where('id', $itemId)->value('is_selected'));
    }

    public function test_clearing_selection_is_rejected_for_todo_list(): void
    {
        $user = User::factory()->create();
        $wishlist = $this->createWishlist($user, false);
        $wishlist->update(['type' => 'todo']);
        $itemId = $wishlist->items()->where('description', 'Плед')->value('id');

        $this->actingAs($user);
        $this->clearSelection($wishlist, $itemId)->assertStatus(422);

        $this->assertTrue((bool) $wishlist->items()->where('id', $itemId)->value('is_selected'));
    }

    public function test_clearing_selection_requires_own_wishlist_and_item(): void
    {
        $user = User::factory()->create();
        $wishlist = $this->createWishlist($user, false);
        $other = $this->createWishlist(User::factory()->create(), false);
        $foreignItemId = $other->items()->where('description', 'Плед')->value('id');

        $this->actingAs($user);
        $this->clearSelection($other, $foreignItemId)->assertNotFound();
        $this->clearSelection($wishlist, $foreignItemId)->assertNotFound();

        $this->assertTrue((bool) $other->items()->where('id', $foreignItemId)->value('is_selected'));
    }

    public function test_clearing_selection_requires_authentication(): void
    {
        $wishlist = $this->createWishlist(User::factory()->create(), false);

        $this->clearSelection($wishlist, $wishlist->items()->value('id'))
            ->assertUnauthorized();
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
