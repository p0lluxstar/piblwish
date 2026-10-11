<?php

namespace Tests\Feature;

use App\Enums\WishlistType;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Архив списков: POST и DELETE /v1/wishlists/{id}/archive.
 *
 * Архивный список не попадает в основной список дашборда, доступен владельцу
 * только для просмотра и не открывается по ссылке. Доступ по ссылке, брони
 * и совместные подарки сохраняются и снова действуют после восстановления.
 */
class WishlistArchiveTest extends TestCase
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

    private function archive(?Wishlist $wishlist = null): void
    {
        $wishlist ??= $this->wishlist;

        $this->actingAs($this->owner)
            ->postJson("/v1/wishlists/{$wishlist->id}/archive")
            ->assertOk()
            ->assertJsonPath('data.id', $wishlist->id);
    }

    private function restore(?Wishlist $wishlist = null): void
    {
        $wishlist ??= $this->wishlist;

        $this->actingAs($this->owner)
            ->deleteJson("/v1/wishlists/{$wishlist->id}/archive")
            ->assertOk()
            ->assertJsonPath('data.archivedAt', null);
    }

    public function test_new_wishlist_is_not_archived(): void
    {
        $this->actingAs($this->owner)
            ->getJson('/v1/wishlists')
            ->assertOk()
            ->assertJsonPath('data.0.archivedAt', null)
            ->assertJsonPath('meta.archivedCount', 0);
    }

    public function test_owner_archives_and_restores_wishlist(): void
    {
        Carbon::setTestNow('2026-10-10 12:00:00');

        $this->actingAs($this->owner)
            ->postJson("/v1/wishlists/{$this->wishlist->id}/archive")
            ->assertOk()
            ->assertJsonPath('data.archivedAt', '2026-10-10T12:00:00+00:00')
            ->assertJsonCount(2, 'data.items');

        $this->restore();

        $this->assertNull($this->wishlist->fresh()->archived_at);
    }

    public function test_repeated_archive_keeps_original_time(): void
    {
        Carbon::setTestNow('2026-10-10 12:00:00');
        $this->archive();

        Carbon::setTestNow('2026-10-11 12:00:00');
        $this->archive();

        $this->assertSame(
            '2026-10-10 12:00:00',
            $this->wishlist->fresh()->archived_at->toDateTimeString()
        );
    }

    public function test_restoring_active_wishlist_changes_nothing(): void
    {
        $this->restore();
    }

    public function test_dashboard_separates_active_and_archived_lists(): void
    {
        $active = Wishlist::create(['user_id' => $this->owner->id, 'title' => 'Новоселье']);
        $older = Wishlist::create(['user_id' => $this->owner->id, 'title' => 'Новый год']);

        Carbon::setTestNow('2026-10-10 12:00:00');
        $this->archive($older);

        Carbon::setTestNow('2026-10-11 12:00:00');
        $this->archive();

        $this->actingAs($this->owner)
            ->getJson('/v1/wishlists')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $active->id)
            ->assertJsonPath('meta.archivedCount', 2);

        // Первым идёт список, перенесённый в архив последним
        $this->actingAs($this->owner)
            ->getJson('/v1/wishlists?archived=1')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $this->wishlist->id)
            ->assertJsonPath('data.1.id', $older->id)
            ->assertJsonPath('data.0.items.0.label', 'Плед')
            ->assertJsonPath('meta.archivedCount', 2);
    }

    public function test_other_user_cannot_archive_or_restore(): void
    {
        $other = User::factory()->create();

        $this->actingAs($other)
            ->postJson("/v1/wishlists/{$this->wishlist->id}/archive")
            ->assertNotFound();

        $this->wishlist->update(['archived_at' => now()]);

        $this->actingAs($other)
            ->deleteJson("/v1/wishlists/{$this->wishlist->id}/archive")
            ->assertNotFound();

        $this->assertNotNull($this->wishlist->fresh()->archived_at);
    }

    public function test_guest_cannot_archive(): void
    {
        $this->postJson("/v1/wishlists/{$this->wishlist->id}/archive")->assertUnauthorized();
    }

    public function test_archived_wishlist_is_read_only(): void
    {
        $todo = Wishlist::create([
            'user_id' => $this->owner->id,
            'type' => WishlistType::Todo,
            'title' => 'Переезд',
        ]);
        $todoItem = $todo->items()->create(['description' => 'Коробки', 'position' => 0]);

        $this->archive();
        $this->archive($todo);

        $this->actingAs($this->owner)
            ->patchJson("/v1/wishlists/{$this->wishlist->id}", ['title' => 'Другое'])
            ->assertStatus(409);

        $this->actingAs($this->owner)
            ->getJson("/v1/wishlists/{$this->wishlist->id}/selections")
            ->assertStatus(409);

        $this->actingAs($this->owner)
            ->deleteJson("/v1/wishlists/{$this->wishlist->id}/items/{$this->itemId('Плед')}/selection", [
                'checkedAt' => now()->toIso8601String(),
            ])
            ->assertStatus(409);

        $this->actingAs($this->owner)
            ->patchJson("/v1/wishlists/{$todo->id}/items/{$todoItem->id}", ['isSelected' => true])
            ->assertStatus(409);

        $this->assertSame('День рождения', $this->wishlist->fresh()->title);
        $this->assertFalse($todoItem->fresh()->is_selected);

        // После восстановления список снова можно изменять
        $this->restore();

        $this->actingAs($this->owner)
            ->patchJson("/v1/wishlists/{$this->wishlist->id}", ['title' => 'Другое'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Другое');
    }

    public function test_archived_wishlist_can_be_deleted(): void
    {
        $this->archive();

        $this->actingAs($this->owner)
            ->deleteJson("/v1/wishlists/{$this->wishlist->id}")
            ->assertOk();

        $this->assertModelMissing($this->wishlist);
    }

    public function test_archived_gift_list_is_not_available_to_guests(): void
    {
        $token = $this->patchJson("/api/v1/shared-wishlists/{$this->wishlist->id}/items", [
            'item_ids' => [$this->itemId('Плед')],
        ])->assertOk()->json('data.reservation.token');

        $this->archive();

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

    public function test_sharing_and_selection_are_restored_with_wishlist(): void
    {
        $token = $this->patchJson("/api/v1/shared-wishlists/{$this->wishlist->id}/items", [
            'item_ids' => [$this->itemId('Плед')],
            'joint_gifts' => [['item_id' => $this->itemId('Плед'), 'name' => 'Аня']],
        ])->assertOk()->json('data.reservation.token');

        $this->archive();

        // Настройка доступа по ссылке в архиве не меняется
        $this->actingAs($this->owner)
            ->getJson('/v1/wishlists?archived=1')
            ->assertJsonPath('data.0.isShared', true);

        $this->restore();

        $this->getJson("/api/v1/shared-wishlists/{$this->wishlist->id}")
            ->assertOk()
            ->assertJsonPath('data.items.0.isSelected', true)
            ->assertJsonPath('data.items.0.jointGift.name', 'Аня');

        $this->postJson("/api/v1/shared-wishlists/{$this->wishlist->id}/reservations/lookup", [
            'tokens' => [$token],
        ])
            ->assertOk()
            ->assertJsonPath('data.0.itemIds', [$this->itemId('Плед')]);
    }

    public function test_archived_todo_list_is_not_available_to_guests(): void
    {
        $todo = Wishlist::create([
            'user_id' => $this->owner->id,
            'type' => WishlistType::Todo,
            'title' => 'Переезд',
            'is_shared' => true,
            'guests_can_check' => true,
            'guest_name_required' => false,
        ]);
        $item = $todo->items()->create(['description' => 'Коробки', 'position' => 0]);

        $this->archive($todo);

        $this->getJson("/api/v1/shared-wishlists/{$todo->id}")->assertNotFound();

        $this->postJson("/api/v1/shared-wishlists/{$todo->id}/items/check", [
            'item_ids' => [$item->id],
        ])->assertNotFound();

        $this->assertFalse($item->fresh()->is_selected);

        $this->restore($todo);

        $this->getJson("/api/v1/shared-wishlists/{$todo->id}")
            ->assertOk()
            ->assertJsonPath('data.guestsCanCheck', true);
    }

    public function test_bookmark_of_archived_wishlist_is_unavailable(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson("/v1/saved-wishlists/{$this->wishlist->id}")->assertOk();

        $this->archive();

        $this->actingAs($user)
            ->getJson('/v1/saved-wishlists')
            ->assertOk()
            ->assertJsonPath('data.0.available', false)
            ->assertJsonPath('data.0.title', null);

        $other = User::factory()->create();

        $this->actingAs($other)->postJson("/v1/saved-wishlists/{$this->wishlist->id}")->assertNotFound();

        $this->restore();

        $this->actingAs($user)
            ->getJson('/v1/saved-wishlists')
            ->assertOk()
            ->assertJsonPath('data.0.available', true)
            ->assertJsonPath('data.0.title', 'День рождения');
    }
}
