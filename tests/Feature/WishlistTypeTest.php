<?php

namespace Tests\Feature;

use App\Enums\WishlistType;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Тип списка: поле type в /v1/wishlists.
 *
 * gift — список желаний (по умолчанию), todo — список дел. У списка дел нет
 * ссылки, цены и приоритета позиций и режима сюрприза, а isSelected означает
 * «выполнено». Список дел недоступен по общей ссылке.
 */
class WishlistTypeTest extends TestCase
{
    use RefreshDatabase;

    private function createTodoList(User $user): Wishlist
    {
        $wishlist = Wishlist::create([
            'user_id' => $user->id,
            'type' => WishlistType::Todo,
            'title' => 'Дела на выходные',
        ]);

        $wishlist->items()->create(['description' => 'Купить продукты', 'position' => 0]);

        return $wishlist;
    }

    public function test_wishlist_without_type_is_gift(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/v1/wishlists', [
                'title' => 'День рождения',
                'items' => [['label' => 'Книга']],
            ])
            ->assertOk()
            ->assertJsonPath('data.type', 'gift');

        $this->assertDatabaseHas('wishlists', [
            'id' => $response->json('data.id'),
            'type' => 'gift',
        ]);
    }

    public function test_todo_list_is_created(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/v1/wishlists', [
                'type' => 'todo',
                'title' => 'Дела на выходные',
                'color' => 'mint',
                'items' => [['label' => 'Купить продукты'], ['label' => 'Позвонить маме']],
            ])
            ->assertOk()
            ->assertJsonPath('data.type', 'todo')
            ->assertJsonPath('data.color', 'mint')
            ->assertJsonPath('data.items.1.label', 'Позвонить маме')
            ->assertJsonPath('data.items.1.isSelected', false);

        $this->assertDatabaseHas('wishlists', [
            'id' => $response->json('data.id'),
            'type' => 'todo',
        ]);
    }

    public function test_unknown_type_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/v1/wishlists', [
                'type' => 'shopping',
                'title' => 'Покупки',
                'items' => [['label' => 'Хлеб']],
            ])
            ->assertStatus(422)
            ->assertJsonPath('data.errors.type.0', 'Недопустимый тип списка');

        $this->assertDatabaseCount('wishlists', 0);
    }

    public function test_gift_fields_are_rejected_for_todo_list(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/v1/wishlists', [
                'type' => 'todo',
                'title' => 'Дела',
                'hideSelections' => true,
                'items' => [[
                    'label' => 'Купить продукты',
                    'url' => 'https://example.com',
                    'priority' => 2,
                    'price' => 500,
                ]],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'hideSelections',
                'items.0.url',
                'items.0.priority',
                'items.0.price',
            ], 'data.errors');

        $this->assertDatabaseCount('wishlists', 0);
    }

    public function test_empty_gift_fields_are_allowed_for_todo_list(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/v1/wishlists', [
                'type' => 'todo',
                'title' => 'Дела',
                'hideSelections' => false,
                'items' => [[
                    'label' => 'Купить продукты',
                    'url' => null,
                    'priority' => null,
                    'price' => null,
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('data.hideSelections', false);
    }

    public function test_todo_item_can_be_marked_done(): void
    {
        $user = User::factory()->create();
        $wishlist = $this->createTodoList($user);
        $item = $wishlist->items()->first();

        $this->actingAs($user)
            ->patchJson("/v1/wishlists/{$wishlist->id}", [
                'items' => [['id' => $item->id, 'label' => 'Купить продукты', 'isSelected' => true]],
            ])
            ->assertOk()
            ->assertJsonPath('data.items.0.isSelected', true);

        $this->assertTrue($item->fresh()->is_selected);
    }

    public function test_update_ignores_gift_fields_of_todo_list(): void
    {
        $user = User::factory()->create();
        $wishlist = $this->createTodoList($user);
        $item = $wishlist->items()->first();

        $this->actingAs($user)
            ->patchJson("/v1/wishlists/{$wishlist->id}", [
                'type' => 'gift',
                'hideSelections' => true,
                'items' => [[
                    'id' => $item->id,
                    'label' => 'Купить продукты',
                    'url' => 'https://example.com',
                    'priority' => 3,
                    'price' => 500,
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('data.type', 'todo')
            ->assertJsonPath('data.hideSelections', false)
            ->assertJsonPath('data.items.0.url', null)
            ->assertJsonPath('data.items.0.priority', null)
            ->assertJsonPath('data.items.0.price', null);

        $this->assertSame(WishlistType::Todo, $wishlist->fresh()->type);
    }

    public function test_todo_list_is_not_available_by_shared_link(): void
    {
        $wishlist = $this->createTodoList(User::factory()->create());
        $item = $wishlist->items()->first();

        $this->getJson("/api/v1/shared-wishlists/{$wishlist->id}")
            ->assertNotFound();

        $this->patchJson("/api/v1/shared-wishlists/{$wishlist->id}/items", [
            'item_ids' => [$item->id],
        ])->assertNotFound();

        $this->postJson("/api/v1/shared-wishlists/{$wishlist->id}/reservations/lookup", [
            'tokens' => [Str::random(40)],
        ])->assertNotFound();

        $this->postJson("/api/v1/shared-wishlists/{$wishlist->id}/reservations/cancel", [
            'token' => Str::random(40),
            'item_ids' => [$item->id],
        ])->assertNotFound();

        $this->assertFalse($item->fresh()->is_selected);
    }
}
