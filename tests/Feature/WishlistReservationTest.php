<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wishlist;
use App\Models\WishlistReservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Брони гостей на общей странице списка.
 *
 * Сохранение выбора возвращает токен брони. По токену гость узнаёт,
 * какие позиции выбрал он, и может отменить выбор любой из них.
 * Редактирование списка владельцем брони не сбрасывает.
 */
class WishlistReservationTest extends TestCase
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
            ['description' => 'Свеча', 'position' => 2],
        ]);
    }

    private function itemId(string $description): string
    {
        return $this->wishlist->items()->where('description', $description)->value('id');
    }

    private function isSelected(string $description): bool
    {
        return (bool) $this->wishlist->items()->where('description', $description)->value('is_selected');
    }

    // Гость выбирает позиции и получает токен брони
    private function reserve(string ...$descriptions): string
    {
        return $this->patchJson("/api/v1/shared-wishlists/{$this->wishlist->id}/items", [
            'item_ids' => array_map(fn ($description) => $this->itemId($description), $descriptions),
        ])->assertOk()->json('data.reservation.token');
    }

    private function cancel(string $token, string ...$descriptions): \Illuminate\Testing\TestResponse
    {
        return $this->postJson("/api/v1/shared-wishlists/{$this->wishlist->id}/reservations/cancel", [
            'token' => $token,
            'item_ids' => array_map(fn ($description) => $this->itemId($description), $descriptions),
        ]);
    }

    public function test_saving_selection_returns_reservation_token(): void
    {
        $response = $this->patchJson("/api/v1/shared-wishlists/{$this->wishlist->id}/items", [
            'item_ids' => [$this->itemId('Плед'), $this->itemId('Книга')],
        ])->assertOk();

        $token = $response->json('data.reservation.token');

        $this->assertSame(40, strlen($token));
        $this->assertEqualsCanonicalizing(
            [$this->itemId('Плед'), $this->itemId('Книга')],
            $response->json('data.reservation.itemIds')
        );

        // В БД хранится хеш, а не сам токен
        $this->assertDatabaseMissing('wishlist_reservations', ['token_hash' => $token]);
        $this->assertDatabaseHas('wishlist_reservations', [
            'wishlist_id' => $this->wishlist->id,
            'token_hash' => WishlistReservation::hashToken($token),
        ]);
    }

    public function test_shared_wishlist_does_not_expose_reservations(): void
    {
        $this->reserve('Плед');

        $this->getJson("/api/v1/shared-wishlists/{$this->wishlist->id}")
            ->assertOk()
            ->assertJsonMissingPath('data.reservation')
            ->assertJsonMissingPath('data.items.0.reservationId');
    }

    public function test_reservations_are_found_by_tokens(): void
    {
        $first = $this->reserve('Плед', 'Книга');
        $second = $this->reserve('Свеча');

        $response = $this->postJson("/api/v1/shared-wishlists/{$this->wishlist->id}/reservations/lookup", [
            'tokens' => [$first, $second, str_repeat('x', 40)],
        ])->assertOk();

        $reservations = collect($response->json('data'))->keyBy('token');

        $this->assertCount(2, $reservations);
        $this->assertEqualsCanonicalizing(
            [$this->itemId('Плед'), $this->itemId('Книга')],
            $reservations[$first]['itemIds']
        );
        $this->assertSame([$this->itemId('Свеча')], $reservations[$second]['itemIds']);
    }

    public function test_token_of_another_wishlist_is_not_found(): void
    {
        $token = $this->reserve('Плед');
        $other = Wishlist::create(['user_id' => $this->owner->id, 'title' => 'Другой']);

        $this->postJson("/api/v1/shared-wishlists/{$other->id}/reservations/lookup", [
            'tokens' => [$token],
        ])->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_guest_can_cancel_one_item_and_keep_another(): void
    {
        $token = $this->reserve('Плед', 'Книга');

        $this->cancel($token, 'Плед')
            ->assertOk()
            ->assertJsonPath('data.reservation.itemIds', [$this->itemId('Книга')])
            ->assertJsonPath('data.items.0.isSelected', false)
            ->assertJsonPath('data.items.1.isSelected', true);

        $this->assertFalse($this->isSelected('Плед'));
        $this->assertTrue($this->isSelected('Книга'));

        // Токен действует для оставшейся позиции
        $this->cancel($token, 'Книга')
            ->assertOk()
            ->assertJsonPath('data.reservation.itemIds', []);

        $this->assertFalse($this->isSelected('Книга'));
    }

    public function test_reservation_without_items_is_deleted(): void
    {
        $token = $this->reserve('Плед');

        $this->cancel($token, 'Плед')->assertOk();

        $this->assertDatabaseCount('wishlist_reservations', 0);
        $this->cancel($token, 'Плед')->assertNotFound();
    }

    public function test_cancelled_item_can_be_selected_again(): void
    {
        $token = $this->reserve('Плед');
        $this->cancel($token, 'Плед')->assertOk();

        $this->reserve('Плед');

        $this->assertTrue($this->isSelected('Плед'));
    }

    public function test_guest_cannot_cancel_item_of_another_reservation(): void
    {
        $mine = $this->reserve('Плед');
        $this->reserve('Книга');

        $this->cancel($mine, 'Плед', 'Книга')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['item_ids'], 'data.errors');

        // Запрос отклонён целиком: своя позиция тоже осталась выбранной
        $this->assertTrue($this->isSelected('Плед'));
        $this->assertTrue($this->isSelected('Книга'));
    }

    public function test_unknown_token_is_rejected(): void
    {
        $this->reserve('Плед');

        $this->cancel(str_repeat('x', 40), 'Плед')
            ->assertNotFound()
            ->assertJsonPath('data.message', 'Бронь не найдена');

        $this->assertTrue($this->isSelected('Плед'));
    }

    public function test_token_must_have_valid_length(): void
    {
        $this->cancel('short', 'Плед')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['token'], 'data.errors');
    }

    public function test_owner_edit_keeps_reservation(): void
    {
        $token = $this->reserve('Плед');
        $ids = $this->wishlist->items()->pluck('id', 'description');

        // Владелец переставляет и переименовывает позиции, одну удаляет
        $this->actingAs($this->owner)
            ->patchJson("/v1/wishlists/{$this->wishlist->id}", [
                'items' => [
                    ['id' => $ids['Книга'], 'label' => 'Книга', 'isSelected' => false],
                    ['id' => $ids['Плед'], 'label' => 'Тёплый плед', 'isSelected' => true],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.items.1.id', $ids['Плед']);

        $this->assertDatabaseMissing('wishlist_items', ['id' => $ids['Свеча']]);

        $this->postJson("/api/v1/shared-wishlists/{$this->wishlist->id}/reservations/lookup", [
            'tokens' => [$token],
        ])->assertJsonPath('data.0.itemIds', [$ids['Плед']]);

        $this->cancel($token, 'Тёплый плед')->assertOk();
        $this->assertFalse($this->isSelected('Тёплый плед'));
    }

    public function test_owner_edit_in_surprise_mode_keeps_reservation(): void
    {
        $this->wishlist->update(['hide_selections' => true]);
        $token = $this->reserve('Плед');
        $ids = $this->wishlist->items()->pluck('id', 'description');

        $this->actingAs($this->owner)
            ->patchJson("/v1/wishlists/{$this->wishlist->id}", [
                'items' => [
                    ['id' => $ids['Плед'], 'label' => 'Плед'],
                    ['id' => $ids['Книга'], 'label' => 'Книга'],
                ],
            ])
            ->assertOk();

        $this->assertTrue($this->isSelected('Плед'));

        $this->postJson("/api/v1/shared-wishlists/{$this->wishlist->id}/reservations/lookup", [
            'tokens' => [$token],
        ])->assertJsonPath('data.0.itemIds', [$ids['Плед']]);
    }

    public function test_owner_unselecting_item_removes_it_from_reservation(): void
    {
        $token = $this->reserve('Плед');
        $ids = $this->wishlist->items()->pluck('id', 'description');

        $this->actingAs($this->owner)
            ->patchJson("/v1/wishlists/{$this->wishlist->id}", [
                'items' => [
                    ['id' => $ids['Плед'], 'label' => 'Плед', 'isSelected' => false],
                ],
            ])
            ->assertOk();

        $this->assertNull($this->wishlist->items()->where('id', $ids['Плед'])->value('reservation_id'));

        $this->postJson("/api/v1/shared-wishlists/{$this->wishlist->id}/reservations/lookup", [
            'tokens' => [$token],
        ])->assertJsonCount(0, 'data');
    }

    public function test_items_without_id_are_created_and_missing_ones_deleted(): void
    {
        $ids = $this->wishlist->items()->pluck('id', 'description');

        $response = $this->actingAs($this->owner)
            ->patchJson("/v1/wishlists/{$this->wishlist->id}", [
                'items' => [
                    ['id' => $ids['Свеча'], 'label' => 'Свеча'],
                    ['label' => 'Чай'],
                    // Повтор id создаёт новую позицию, а не перезаписывает ту же
                    ['id' => $ids['Свеча'], 'label' => 'Ещё свеча'],
                ],
            ])
            ->assertOk()
            ->assertJsonCount(3, 'data.items')
            ->assertJsonPath('data.items.0.id', $ids['Свеча'])
            ->assertJsonPath('data.items.1.label', 'Чай')
            ->assertJsonPath('data.items.2.label', 'Ещё свеча');

        $this->assertNotSame($ids['Свеча'], $response->json('data.items.2.id'));
        $this->assertDatabaseMissing('wishlist_items', ['id' => $ids['Плед']]);
        $this->assertDatabaseMissing('wishlist_items', ['id' => $ids['Книга']]);
    }
}
