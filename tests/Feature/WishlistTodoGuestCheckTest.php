<?php

namespace Tests\Feature;

use App\Enums\WishlistType;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Отметка дел гостями по ссылке: флаг guestsCanCheck и POST /items/check.
 *
 * Гость отмечает дела только в списке дел с доступом по ссылке, где владелец
 * это разрешил. Снять отметку гость не может: уже выполненное дело не изменяется.
 */
class WishlistTodoGuestCheckTest extends TestCase
{
    use RefreshDatabase;

    private function createTodoList(bool $isShared = true, bool $guestsCanCheck = true): Wishlist
    {
        $wishlist = Wishlist::create([
            'user_id' => User::factory()->create()->id,
            'type' => WishlistType::Todo,
            'title' => 'Покупки',
            'is_shared' => $isShared,
            'guests_can_check' => $guestsCanCheck,
        ]);

        $wishlist->items()->create(['description' => 'Хлеб', 'position' => 0, 'is_selected' => true]);
        $wishlist->items()->create(['description' => 'Молоко', 'position' => 1]);
        $wishlist->items()->create(['description' => 'Сыр', 'position' => 2]);

        return $wishlist;
    }

    private function checkUrl(Wishlist $wishlist): string
    {
        return "/api/v1/shared-wishlists/{$wishlist->id}/items/check";
    }

    public function test_owner_enables_guest_check(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/v1/wishlists', [
                'type' => 'todo',
                'title' => 'Покупки',
                'isShared' => true,
                'guestsCanCheck' => true,
                'items' => [['label' => 'Хлеб']],
            ])
            ->assertOk()
            ->assertJsonPath('data.guestsCanCheck', true);

        $id = $response->json('data.id');

        $this->actingAs($user)
            ->patchJson("/v1/wishlists/{$id}", ['guestsCanCheck' => false])
            ->assertOk()
            ->assertJsonPath('data.guestsCanCheck', false);
    }

    public function test_guest_check_is_rejected_for_gift_list(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/v1/wishlists', [
                'title' => 'День рождения',
                'guestsCanCheck' => true,
                'items' => [['label' => 'Книга']],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('guestsCanCheck', 'data.errors');
    }

    public function test_shared_page_tells_whether_guest_can_check(): void
    {
        $this->getJson('/api/v1/shared-wishlists/'.$this->createTodoList()->id)
            ->assertOk()
            ->assertJsonPath('data.guestsCanCheck', true);

        $this->getJson('/api/v1/shared-wishlists/'.$this->createTodoList(guestsCanCheck: false)->id)
            ->assertOk()
            ->assertJsonPath('data.guestsCanCheck', false);
    }

    public function test_guest_checks_items(): void
    {
        $wishlist = $this->createTodoList();
        [$bread, $milk, $cheese] = $wishlist->items()->get()->all();

        // Уже выполненное дело в запросе не мешает отметить остальные
        $this->postJson($this->checkUrl($wishlist), ['item_ids' => [$bread->id, $milk->id], 'name' => ' Иван '])
            ->assertOk()
            ->assertJsonPath('data.type', 'todo')
            ->assertJsonPath('data.items.0.isSelected', true)
            // Хлеб отметил владелец ещё до запроса гостя
            ->assertJsonPath('data.items.0.checkedBy', ['guest' => false, 'name' => null])
            ->assertJsonPath('data.items.1.isSelected', true)
            ->assertJsonPath('data.items.1.checkedBy', ['guest' => true, 'name' => 'Иван'])
            ->assertJsonPath('data.items.2.isSelected', false)
            ->assertJsonPath('data.items.2.checkedBy', null);

        $this->assertTrue($milk->fresh()->is_selected);
        $this->assertFalse($cheese->fresh()->is_selected);
    }

    public function test_name_is_required_by_default(): void
    {
        $wishlist = $this->createTodoList();
        $milk = $wishlist->items()->where('is_selected', false)->first();

        $this->getJson("/api/v1/shared-wishlists/{$wishlist->id}")
            ->assertJsonPath('data.guestNameRequired', true);

        foreach ([[], ['name' => '   ']] as $payload) {
            $this->postJson($this->checkUrl($wishlist), ['item_ids' => [$milk->id]] + $payload)
                ->assertUnprocessable()
                ->assertJsonValidationErrors('name', 'data.errors');
        }

        $this->assertFalse($milk->fresh()->is_selected);
    }

    public function test_guest_checks_without_name_when_it_is_optional(): void
    {
        $wishlist = $this->createTodoList();
        $wishlist->update(['guest_name_required' => false]);
        $milk = $wishlist->items()->where('is_selected', false)->first();

        $this->postJson($this->checkUrl($wishlist), ['item_ids' => [$milk->id]])
            ->assertOk()
            ->assertJsonPath('data.guestNameRequired', false)
            ->assertJsonPath('data.items.1.checkedBy', ['guest' => true, 'name' => null]);
    }

    public function test_second_guest_does_not_replace_name(): void
    {
        $wishlist = $this->createTodoList();
        $milk = $wishlist->items()->where('is_selected', false)->first();

        $this->postJson($this->checkUrl($wishlist), ['item_ids' => [$milk->id], 'name' => 'Иван'])->assertOk();
        $this->postJson($this->checkUrl($wishlist), ['item_ids' => [$milk->id], 'name' => 'Аня'])->assertOk();

        $this->assertSame('Иван', $milk->fresh()->checked_by_name);
    }

    public function test_owner_sees_who_checked_and_unchecking_clears_it(): void
    {
        $wishlist = $this->createTodoList();
        $owner = $wishlist->user;
        [$bread, $milk, $cheese] = $wishlist->items()->get()->all();

        $this->postJson($this->checkUrl($wishlist), ['item_ids' => [$milk->id], 'name' => 'Иван'])->assertOk();

        $this->actingAs($owner)
            ->getJson('/v1/wishlists')
            ->assertJsonPath('data.0.guestNameRequired', true)
            ->assertJsonPath('data.0.items.1.checkedBy', ['guest' => true, 'name' => 'Иван']);

        // Сохранение формы: молоко осталось выполненным — имя сохраняется,
        // сыр владелец отметил сам — имени нет
        $this->actingAs($owner)
            ->patchJson("/v1/wishlists/{$wishlist->id}", ['items' => [
                ['id' => $bread->id, 'label' => 'Хлеб', 'isSelected' => true],
                ['id' => $milk->id, 'label' => 'Молоко', 'isSelected' => true],
                ['id' => $cheese->id, 'label' => 'Сыр', 'isSelected' => true],
            ]])
            ->assertOk()
            ->assertJsonPath('data.items.1.checkedBy', ['guest' => true, 'name' => 'Иван'])
            ->assertJsonPath('data.items.2.checkedBy', ['guest' => false, 'name' => null]);

        // Владелец снял отметку с карточки: автор отметки удаляется
        $this->actingAs($owner)
            ->patchJson("/v1/wishlists/{$wishlist->id}/items/{$milk->id}", ['isSelected' => false])
            ->assertOk();

        $milk->refresh();
        $this->assertFalse($milk->checked_by_guest);
        $this->assertNull($milk->checked_by_name);
    }

    public function test_guest_check_is_unavailable_without_permission(): void
    {
        foreach ([$this->createTodoList(guestsCanCheck: false), $this->createTodoList(isShared: false)] as $wishlist) {
            $item = $wishlist->items()->where('is_selected', false)->first();

            $this->postJson($this->checkUrl($wishlist), ['item_ids' => [$item->id], 'name' => 'Иван'])
                ->assertNotFound();

            $this->assertFalse($item->fresh()->is_selected);
        }
    }

    public function test_guest_check_is_unavailable_for_gift_list(): void
    {
        $wishlist = Wishlist::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'День рождения',
            'guests_can_check' => true,
        ]);
        $item = $wishlist->items()->create(['description' => 'Книга', 'position' => 0]);

        $this->postJson($this->checkUrl($wishlist), ['item_ids' => [$item->id], 'name' => 'Иван'])
            ->assertNotFound();

        $this->assertFalse($item->fresh()->is_selected);
    }

    public function test_item_of_another_list_is_rejected(): void
    {
        $wishlist = $this->createTodoList();
        $milk = $wishlist->items()->where('is_selected', false)->first();
        $foreign = $this->createTodoList()->items()->where('is_selected', false)->first();

        $this->postJson($this->checkUrl($wishlist), ['item_ids' => [$milk->id, $foreign->id], 'name' => 'Иван'])
            ->assertUnprocessable();

        // Запрос отклоняется целиком
        $this->assertFalse($milk->fresh()->is_selected);
        $this->assertFalse($foreign->fresh()->is_selected);
    }
}
