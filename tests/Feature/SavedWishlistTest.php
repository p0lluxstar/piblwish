<?php

namespace Tests\Feature;

use App\Enums\WishlistType;
use App\Models\SavedWishlist;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Раздел «Чужие списки»: /v1/saved-wishlists.
 *
 * Пользователь добавляет к себе чужой список, который открывается по ссылке:
 * список желаний или список дел с включённым доступом. Свой список, заметку
 * и закрытый список дел добавить нельзя. Если владелец закрыл доступ уже после
 * добавления, закладка остаётся, но отдаётся без содержимого списка.
 */
class SavedWishlistTest extends TestCase
{
    use RefreshDatabase;

    private function createWishlist(User $owner, array $attributes = []): Wishlist
    {
        $wishlist = Wishlist::create([
            'user_id' => $owner->id,
            'title' => 'День рождения',
            'color' => 'mint',
            'due_date' => '2026-12-31',
            ...$attributes,
        ]);

        $wishlist->items()->create(['description' => 'Книга', 'position' => 0, 'is_selected' => true]);
        $wishlist->items()->create(['description' => 'Шарф', 'position' => 1]);

        return $wishlist;
    }

    public function test_guest_cannot_use_saved_wishlists(): void
    {
        $wishlist = $this->createWishlist(User::factory()->create());

        $this->getJson('/v1/saved-wishlists')->assertUnauthorized();
        $this->postJson("/v1/saved-wishlists/{$wishlist->id}")->assertUnauthorized();
    }

    public function test_user_saves_wishlist_and_sees_summary(): void
    {
        $owner = User::factory()->create(['username' => 'anna']);
        $user = User::factory()->create();
        $wishlist = $this->createWishlist($owner);

        $this->actingAs($user)
            ->postJson("/v1/saved-wishlists/{$wishlist->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $wishlist->id)
            ->assertJsonPath('data.available', true);

        $this->actingAs($user)
            ->getJson('/v1/saved-wishlists')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $wishlist->id)
            ->assertJsonPath('data.0.type', 'gift')
            ->assertJsonPath('data.0.title', 'День рождения')
            ->assertJsonPath('data.0.color', 'mint')
            ->assertJsonPath('data.0.dueDate', '2026-12-31')
            ->assertJsonPath('data.0.username', 'anna')
            ->assertJsonPath('data.0.itemsCount', 2)
            ->assertJsonPath('data.0.selectedCount', 1)
            // Позиции в разделе не отдаются: они открываются на общей странице
            ->assertJsonMissingPath('data.0.items');
    }

    public function test_saving_twice_keeps_one_bookmark(): void
    {
        $user = User::factory()->create();
        $wishlist = $this->createWishlist(User::factory()->create());

        $this->actingAs($user)->postJson("/v1/saved-wishlists/{$wishlist->id}")->assertOk();
        $this->actingAs($user)->postJson("/v1/saved-wishlists/{$wishlist->id}")->assertOk();

        $this->assertSame(1, SavedWishlist::count());
    }

    public function test_own_wishlist_cannot_be_saved(): void
    {
        $user = User::factory()->create();
        $wishlist = $this->createWishlist($user);

        $this->actingAs($user)
            ->postJson("/v1/saved-wishlists/{$wishlist->id}")
            ->assertUnprocessable()
            ->assertJsonPath('data.errors.wishlist.0', 'Свой список нельзя добавить в чужие');

        $this->assertSame(0, SavedWishlist::count());
    }

    public function test_note_and_private_todo_cannot_be_saved(): void
    {
        $owner = User::factory()->create();
        $user = User::factory()->create();
        $note = Wishlist::create(['user_id' => $owner->id, 'type' => WishlistType::Note, 'content' => 'Текст']);
        $todo = $this->createWishlist($owner, ['type' => WishlistType::Todo]);

        $this->actingAs($user)->postJson("/v1/saved-wishlists/{$note->id}")->assertNotFound();
        $this->actingAs($user)->postJson("/v1/saved-wishlists/{$todo->id}")->assertNotFound();
        $this->actingAs($user)->postJson('/v1/saved-wishlists/unknown')->assertNotFound();

        $this->assertSame(0, SavedWishlist::count());
    }

    public function test_shared_todo_can_be_saved(): void
    {
        $user = User::factory()->create();
        $todo = $this->createWishlist(User::factory()->create(), [
            'type' => WishlistType::Todo,
            'is_shared' => true,
        ]);

        $this->actingAs($user)
            ->postJson("/v1/saved-wishlists/{$todo->id}")
            ->assertOk()
            ->assertJsonPath('data.type', 'todo')
            ->assertJsonPath('data.selectedCount', 1);
    }

    public function test_closed_todo_is_returned_without_content(): void
    {
        $owner = User::factory()->create(['username' => 'anna']);
        $user = User::factory()->create();
        $todo = $this->createWishlist($owner, [
            'type' => WishlistType::Todo,
            'is_shared' => true,
        ]);

        $this->actingAs($user)->postJson("/v1/saved-wishlists/{$todo->id}")->assertOk();

        $todo->update(['is_shared' => false]);

        $this->actingAs($user)
            ->getJson('/v1/saved-wishlists')
            ->assertOk()
            ->assertJsonPath('data.0.id', $todo->id)
            ->assertJsonPath('data.0.available', false)
            ->assertJsonPath('data.0.username', 'anna')
            ->assertJsonPath('data.0.title', null)
            ->assertJsonPath('data.0.itemsCount', null);
    }

    public function test_user_sees_only_own_bookmarks(): void
    {
        $wishlist = $this->createWishlist(User::factory()->create());
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($other)->postJson("/v1/saved-wishlists/{$wishlist->id}")->assertOk();

        $this->actingAs($user)
            ->getJson('/v1/saved-wishlists')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_bookmark_is_removed(): void
    {
        $user = User::factory()->create();
        $wishlist = $this->createWishlist(User::factory()->create());

        $this->actingAs($user)->postJson("/v1/saved-wishlists/{$wishlist->id}")->assertOk();

        $this->actingAs($user)
            ->deleteJson("/v1/saved-wishlists/{$wishlist->id}")
            ->assertOk();

        // Повторное удаление не считается ошибкой
        $this->actingAs($user)
            ->deleteJson("/v1/saved-wishlists/{$wishlist->id}")
            ->assertOk();

        $this->assertSame(0, SavedWishlist::count());
    }

    public function test_bookmarks_are_deleted_with_account(): void
    {
        $user = User::factory()->create();
        $wishlist = $this->createWishlist(User::factory()->create());

        $this->actingAs($user)->postJson("/v1/saved-wishlists/{$wishlist->id}")->assertOk();

        $this->actingAs($user)->deleteJson('/v1/user')->assertOk();

        $this->assertSame(0, SavedWishlist::count());
        // Сам чужой список не затрагивается
        $this->assertNotNull($wishlist->fresh());
    }

    public function test_bookmark_disappears_when_wishlist_is_deleted(): void
    {
        $owner = User::factory()->create();
        $user = User::factory()->create();
        $wishlist = $this->createWishlist($owner);

        $this->actingAs($user)->postJson("/v1/saved-wishlists/{$wishlist->id}")->assertOk();

        $this->actingAs($owner)->deleteJson("/v1/wishlists/{$wishlist->id}")->assertOk();

        $this->assertSame(0, SavedWishlist::count());
    }
}
