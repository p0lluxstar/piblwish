<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Совместный подарок: гость выбирает позицию и предлагает остальным
 * гостям подарить её вместе. Имя, контакт и комментарий организатора
 * видны только на общей странице, владельцу списка они не отдаются.
 */
class WishlistJointGiftTest extends TestCase
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
            ['description' => 'Велосипед', 'position' => 0],
            ['description' => 'Книга', 'position' => 1],
        ]);
    }

    private function itemId(string $description): string
    {
        return $this->wishlist->items()->where('description', $description)->value('id');
    }

    private function select(array $itemIds, array $jointGifts): TestResponse
    {
        return $this->patchJson("/api/v1/shared-wishlists/{$this->wishlist->id}/items", [
            'item_ids' => $itemIds,
            'joint_gifts' => $jointGifts,
        ]);
    }

    // Гость выбирает велосипед как совместный подарок и книгу как обычный
    private function selectBicycleTogether(): string
    {
        return $this->select(
            [$this->itemId('Велосипед'), $this->itemId('Книга')],
            [[
                'item_id' => $this->itemId('Велосипед'),
                'name' => 'Иван, коллега Ани',
                'contact' => '@ivan_k',
                'comment' => 'Собираем до 10 октября',
            ]]
        )->assertOk()->json('data.reservation.token');
    }

    public function test_guest_creates_joint_gift(): void
    {
        $this->selectBicycleTogether();

        $this->getJson("/api/v1/shared-wishlists/{$this->wishlist->id}")
            ->assertOk()
            ->assertJsonPath('data.items.0.isSelected', true)
            ->assertJsonPath('data.items.0.jointGift', [
                'name' => 'Иван, коллега Ани',
                'contact' => '@ivan_k',
                'comment' => 'Собираем до 10 октября',
            ])
            ->assertJsonPath('data.items.1.isSelected', true)
            ->assertJsonPath('data.items.1.jointGift', null);
    }

    public function test_contact_and_comment_are_optional(): void
    {
        $this->select([$this->itemId('Велосипед')], [[
            'item_id' => $this->itemId('Велосипед'),
            'name' => 'Иван',
        ]])
            ->assertOk()
            ->assertJsonPath('data.items.0.jointGift', [
                'name' => 'Иван',
                'contact' => null,
                'comment' => null,
            ]);
    }

    public function test_selection_without_joint_gifts_still_works(): void
    {
        $this->patchJson("/api/v1/shared-wishlists/{$this->wishlist->id}/items", [
            'item_ids' => [$this->itemId('Книга')],
        ])
            ->assertOk()
            ->assertJsonPath('data.items.1.isSelected', true)
            ->assertJsonPath('data.items.1.jointGift', null);

        $this->assertDatabaseCount('wishlist_joint_gifts', 0);
    }

    public function test_joint_gift_item_must_be_selected_in_same_request(): void
    {
        $this->select([$this->itemId('Книга')], [[
            'item_id' => $this->itemId('Велосипед'),
            'name' => 'Иван',
        ]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['joint_gifts.0.item_id'], 'data.errors');

        // Запрос отклонён целиком: книга тоже не выбрана
        $this->assertFalse((bool) $this->wishlist->items()->where('description', 'Книга')->value('is_selected'));
        $this->assertDatabaseCount('wishlist_joint_gifts', 0);
    }

    public function test_organizer_name_is_required_and_limited(): void
    {
        $this->select([$this->itemId('Велосипед')], [[
            'item_id' => $this->itemId('Велосипед'),
            'name' => '',
            'contact' => str_repeat('a', 101),
            'comment' => str_repeat('a', 201),
        ]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(
                ['joint_gifts.0.name', 'joint_gifts.0.contact', 'joint_gifts.0.comment'],
                'data.errors'
            );
    }

    public function test_joint_gift_is_rolled_back_when_item_already_selected(): void
    {
        $this->patchJson("/api/v1/shared-wishlists/{$this->wishlist->id}/items", [
            'item_ids' => [$this->itemId('Велосипед')],
        ])->assertOk();

        $this->select([$this->itemId('Велосипед')], [[
            'item_id' => $this->itemId('Велосипед'),
            'name' => 'Иван',
        ]])->assertStatus(422);

        $this->assertDatabaseCount('wishlist_joint_gifts', 0);
    }

    public function test_owner_never_sees_organizer_data(): void
    {
        $this->selectBicycleTogether();

        // Режим сюрприза выключен: владелец видит отметку, но не данные организатора
        $this->actingAs($this->owner)
            ->getJson('/v1/wishlists')
            ->assertOk()
            ->assertJsonPath('data.0.items.0.isSelected', true)
            ->assertJsonMissingPath('data.0.items.0.jointGift');

        $this->wishlist->update(['hide_selections' => true]);

        $this->actingAs($this->owner)
            ->getJson('/v1/wishlists')
            ->assertOk()
            ->assertJsonMissingPath('data.0.items.0.isSelected')
            ->assertJsonMissingPath('data.0.items.0.jointGift');
    }

    public function test_cancelling_selection_removes_joint_gift(): void
    {
        $token = $this->selectBicycleTogether();

        $this->postJson("/api/v1/shared-wishlists/{$this->wishlist->id}/reservations/cancel", [
            'token' => $token,
            'item_ids' => [$this->itemId('Велосипед')],
        ])
            ->assertOk()
            ->assertJsonPath('data.items.0.isSelected', false)
            ->assertJsonPath('data.items.0.jointGift', null);

        $this->assertDatabaseCount('wishlist_joint_gifts', 0);
    }

    public function test_owner_unselecting_item_removes_joint_gift(): void
    {
        $this->selectBicycleTogether();
        $ids = $this->wishlist->items()->pluck('id', 'description');

        $this->actingAs($this->owner)
            ->patchJson("/v1/wishlists/{$this->wishlist->id}", [
                'items' => [
                    ['id' => $ids['Велосипед'], 'label' => 'Велосипед', 'isSelected' => false],
                    ['id' => $ids['Книга'], 'label' => 'Книга', 'isSelected' => true],
                ],
            ])
            ->assertOk();

        $this->assertDatabaseCount('wishlist_joint_gifts', 0);
    }

    public function test_owner_edit_in_surprise_mode_keeps_joint_gift(): void
    {
        $this->wishlist->update(['hide_selections' => true]);
        $this->selectBicycleTogether();
        $ids = $this->wishlist->items()->pluck('id', 'description');

        $this->actingAs($this->owner)
            ->patchJson("/v1/wishlists/{$this->wishlist->id}", [
                'items' => [
                    ['id' => $ids['Велосипед'], 'label' => 'Велосипед'],
                    ['id' => $ids['Книга'], 'label' => 'Книга'],
                ],
            ])
            ->assertOk();

        $this->assertDatabaseHas('wishlist_joint_gifts', ['item_id' => $ids['Велосипед']]);
    }

    private function updateJointGift(string $token, string $description, ?array $jointGift): TestResponse
    {
        $itemId = $this->itemId($description);

        return $this->putJson("/api/v1/shared-wishlists/{$this->wishlist->id}/items/{$itemId}/joint-gift", [
            'token' => $token,
            'joint_gift' => $jointGift,
        ]);
    }

    public function test_organizer_edits_joint_gift(): void
    {
        $token = $this->selectBicycleTogether();

        $this->updateJointGift($token, 'Велосипед', [
            'name' => 'Иван Петров',
            'contact' => '@ivan_petrov',
        ])
            ->assertOk()
            ->assertJsonPath('data.items.0.isSelected', true)
            ->assertJsonPath('data.items.0.jointGift', [
                'name' => 'Иван Петров',
                'contact' => '@ivan_petrov',
                'comment' => null,
            ]);

        $this->assertDatabaseCount('wishlist_joint_gifts', 1);
    }

    public function test_organizer_adds_joint_gift_to_selected_item(): void
    {
        $token = $this->selectBicycleTogether();

        $this->updateJointGift($token, 'Книга', ['name' => 'Иван'])
            ->assertOk()
            ->assertJsonPath('data.items.1.jointGift.name', 'Иван');
    }

    public function test_organizer_removes_joint_gift_and_keeps_selection(): void
    {
        $token = $this->selectBicycleTogether();

        $this->updateJointGift($token, 'Велосипед', null)
            ->assertOk()
            ->assertJsonPath('data.items.0.isSelected', true)
            ->assertJsonPath('data.items.0.jointGift', null);

        $this->assertDatabaseCount('wishlist_joint_gifts', 0);
    }

    public function test_guest_cannot_edit_joint_gift_of_another_reservation(): void
    {
        $this->selectBicycleTogether();
        $this->wishlist->items()->create(['description' => 'Чай', 'position' => 2]);
        $other = $this->patchJson("/api/v1/shared-wishlists/{$this->wishlist->id}/items", [
            'item_ids' => [$this->itemId('Чай')],
        ])->json('data.reservation.token');

        $this->updateJointGift($other, 'Велосипед', ['name' => 'Мошенник', 'contact' => '@fake'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['item'], 'data.errors');

        $this->updateJointGift(str_repeat('x', 40), 'Велосипед', null)->assertNotFound();

        $this->assertDatabaseHas('wishlist_joint_gifts', ['organizer_name' => 'Иван, коллега Ани']);
    }

    public function test_edited_joint_gift_is_validated(): void
    {
        $token = $this->selectBicycleTogether();

        $this->updateJointGift($token, 'Велосипед', ['name' => '', 'comment' => str_repeat('a', 201)])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['joint_gift.name', 'joint_gift.comment'], 'data.errors');

        // Поле joint_gift обязательно: null нужно передать явно, чтобы убрать совместный подарок
        $itemId = $this->itemId('Велосипед');
        $this->putJson("/api/v1/shared-wishlists/{$this->wishlist->id}/items/{$itemId}/joint-gift", [
            'token' => $token,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['joint_gift'], 'data.errors');
    }

    public function test_deleting_item_removes_joint_gift(): void
    {
        $this->selectBicycleTogether();
        $ids = $this->wishlist->items()->pluck('id', 'description');

        $this->actingAs($this->owner)
            ->patchJson("/v1/wishlists/{$this->wishlist->id}", [
                'items' => [
                    ['id' => $ids['Книга'], 'label' => 'Книга', 'isSelected' => true],
                ],
            ])
            ->assertOk();

        $this->assertDatabaseCount('wishlist_joint_gifts', 0);
    }
}
