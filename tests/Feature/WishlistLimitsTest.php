<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Ограничения объёма (config/wishlist.php): не больше 100 позиций в списке
 * и 100 списков у пользователя вместе с архивом. Массивы в гостевых запросах
 * и в удалении архива ограничены так же. Без этих лимитов один аккаунт или гость
 * мог бы одним запросом записать в базу десятки тысяч строк.
 */
class WishlistLimitsTest extends TestCase
{
    use RefreshDatabase;

    private const MAX = 100;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
    }

    private function items(int $count): array
    {
        return array_map(fn (int $i): array => ['label' => "Позиция {$i}"], range(1, $count));
    }

    private function ulids(int $count): array
    {
        return array_map(fn (): string => (string) Str::ulid(), range(1, $count));
    }

    private function makeWishlists(int $count, ?User $user = null, bool $archived = false): void
    {
        foreach (range(1, $count) as $i) {
            Wishlist::create([
                'user_id' => ($user ?? $this->owner)->id,
                'title' => "Список {$i}",
                'archived_at' => $archived ? now() : null,
            ]);
        }
    }

    private function sharedWishlist(): Wishlist
    {
        $wishlist = Wishlist::create(['user_id' => $this->owner->id, 'title' => 'День рождения']);
        $wishlist->items()->create(['description' => 'Плед', 'position' => 0]);

        return $wishlist;
    }

    public function test_create_accepts_max_items_and_rejects_more(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/v1/wishlists', ['title' => 'Большой', 'items' => $this->items(self::MAX)])
            ->assertOk();

        $this->actingAs($this->owner)
            ->postJson('/v1/wishlists', ['title' => 'Слишком большой', 'items' => $this->items(self::MAX + 1)])
            ->assertStatus(422)
            ->assertJsonPath('data.errors.items.0', 'В списке может быть не больше 100 позиций');

        $this->assertDatabaseCount('wishlists', 1);
        $this->assertDatabaseCount('wishlist_items', self::MAX);
    }

    public function test_update_accepts_max_items_and_rejects_more(): void
    {
        $wishlist = $this->sharedWishlist();

        $this->actingAs($this->owner)
            ->patchJson("/v1/wishlists/{$wishlist->id}", ['items' => $this->items(self::MAX + 1)])
            ->assertStatus(422)
            ->assertJsonPath('data.errors.items.0', 'В списке может быть не больше 100 позиций');

        $this->assertSame(1, $wishlist->items()->count());

        $this->actingAs($this->owner)
            ->patchJson("/v1/wishlists/{$wishlist->id}", ['items' => $this->items(self::MAX)])
            ->assertOk();

        $this->assertSame(self::MAX, $wishlist->items()->count());
    }

    public function test_create_rejected_when_user_has_max_wishlists_including_archive(): void
    {
        $this->makeWishlists(self::MAX - 10);
        $this->makeWishlists(10, archived: true);

        $this->actingAs($this->owner)
            ->postJson('/v1/wishlists', ['title' => 'Сто первый', 'items' => $this->items(1)])
            ->assertStatus(422)
            ->assertJsonPath(
                'data.errors.wishlist.0',
                'Можно создать не больше 100 списков. Удалите ненужные списки или очистите архив'
            );

        $this->assertSame(self::MAX, $this->owner->wishlists()->count());
    }

    public function test_note_also_counts_towards_wishlist_limit(): void
    {
        $this->makeWishlists(self::MAX);

        $this->actingAs($this->owner)
            ->postJson('/v1/wishlists', ['type' => 'note', 'content' => 'Заметка'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('wishlist', 'data.errors');
    }

    public function test_last_wishlist_below_limit_is_created(): void
    {
        $this->makeWishlists(self::MAX - 1);

        $this->actingAs($this->owner)
            ->postJson('/v1/wishlists', ['title' => 'Сотый', 'items' => $this->items(1)])
            ->assertOk();

        $this->assertSame(self::MAX, $this->owner->wishlists()->count());
    }

    public function test_other_users_wishlists_do_not_count(): void
    {
        $this->makeWishlists(self::MAX, User::factory()->create());

        $this->actingAs($this->owner)
            ->postJson('/v1/wishlists', ['title' => 'Первый', 'items' => $this->items(1)])
            ->assertOk();
    }

    public function test_guest_selection_rejects_too_many_items(): void
    {
        $wishlist = $this->sharedWishlist();

        $this->patchJson("/api/v1/shared-wishlists/{$wishlist->share_token}/items", [
            'item_ids' => $this->ulids(self::MAX + 1),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('item_ids', 'data.errors');

        $this->assertDatabaseCount('wishlist_reservations', 0);
    }

    public function test_guest_selection_rejects_too_many_joint_gifts(): void
    {
        $wishlist = $this->sharedWishlist();
        $itemIds = $this->ulids(self::MAX + 1);

        $this->patchJson("/api/v1/shared-wishlists/{$wishlist->share_token}/items", [
            'item_ids' => array_slice($itemIds, 0, self::MAX),
            'joint_gifts' => array_map(fn (string $id): array => ['item_id' => $id, 'name' => 'Аня'], $itemIds),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('joint_gifts', 'data.errors');
    }

    public function test_guest_todo_check_rejects_too_many_items(): void
    {
        $wishlist = $this->sharedWishlist();

        $this->postJson("/api/v1/shared-wishlists/{$wishlist->share_token}/items/check", [
            'item_ids' => $this->ulids(self::MAX + 1),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('item_ids', 'data.errors');
    }

    public function test_reservation_cancel_rejects_too_many_items(): void
    {
        $wishlist = $this->sharedWishlist();

        $this->postJson("/api/v1/shared-wishlists/{$wishlist->share_token}/reservations/cancel", [
            'token' => str_repeat('a', 40),
            'item_ids' => $this->ulids(self::MAX + 1),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('item_ids', 'data.errors');
    }

    public function test_archive_delete_rejects_too_many_ids(): void
    {
        $this->actingAs($this->owner)
            ->deleteJson('/v1/wishlists/archived', [
                'ids' => array_map(fn (int $i): string => "id-{$i}", range(1, self::MAX + 1)),
            ])
            ->assertStatus(422)
            ->assertJsonPath('data.errors.ids.0', 'Слишком много списков для удаления за раз');
    }
}
