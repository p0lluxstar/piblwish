<?php

namespace Tests\Feature;

use App\Enums\WishlistType;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Заметка: тип note в /v1/wishlists.
 *
 * У заметки нет названия, позиций и режима сюрприза: её содержимое — текст
 * в поле content. Изменяются только цвет и текст. Заметка недоступна
 * по общей ссылке.
 */
class WishlistNoteTest extends TestCase
{
    use RefreshDatabase;

    private function createNote(User $user): Wishlist
    {
        return Wishlist::create([
            'user_id' => $user->id,
            'type' => WishlistType::Note,
            'content' => "Код домофона 1234\nПодъезд 2",
        ]);
    }

    public function test_note_is_created(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/v1/wishlists', [
                'type' => 'note',
                'color' => 'lemon',
                'content' => "Код домофона 1234\nПодъезд 2",
            ])
            ->assertOk()
            ->assertJsonPath('data.type', 'note')
            ->assertJsonPath('data.title', null)
            ->assertJsonPath('data.color', 'lemon')
            ->assertJsonPath('data.content', "Код домофона 1234\nПодъезд 2")
            ->assertJsonPath('data.items', []);

        $this->assertDatabaseHas('wishlists', [
            'id' => $response->json('data.id'),
            'type' => 'note',
            'title' => null,
        ]);
        $this->assertDatabaseCount('wishlist_items', 0);
    }

    public function test_note_requires_content(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/v1/wishlists', ['type' => 'note', 'content' => '   '])
            ->assertStatus(422)
            ->assertJsonPath('data.errors.content.0', 'Текст заметки обязателен');

        $this->assertDatabaseCount('wishlists', 0);
    }

    public function test_note_content_length_is_limited(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/v1/wishlists', [
                'type' => 'note',
                'content' => str_repeat('а', 5001),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['content'], 'data.errors');
    }

    public function test_list_fields_are_rejected_for_note(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/v1/wishlists', [
                'type' => 'note',
                'title' => 'Заметка',
                'content' => 'Текст',
                'hideSelections' => true,
                'items' => [['label' => 'Хлеб']],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'hideSelections', 'items'], 'data.errors');

        $this->assertDatabaseCount('wishlists', 0);
    }

    public function test_content_is_rejected_for_lists(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/v1/wishlists', [
                'title' => 'День рождения',
                'content' => 'Текст',
                'items' => [['label' => 'Книга']],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['content'], 'data.errors');
    }

    public function test_list_still_requires_title_and_items(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/v1/wishlists', ['type' => 'todo'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'items'], 'data.errors');
    }

    public function test_note_content_and_color_are_updated(): void
    {
        $user = User::factory()->create();
        $note = $this->createNote($user);

        $this->actingAs($user)
            ->patchJson("/v1/wishlists/{$note->id}", [
                'color' => 'mint',
                'content' => 'Новый текст',
            ])
            ->assertOk()
            ->assertJsonPath('data.color', 'mint')
            ->assertJsonPath('data.content', 'Новый текст');
    }

    public function test_update_ignores_list_fields_of_note(): void
    {
        $user = User::factory()->create();
        $note = $this->createNote($user);

        $this->actingAs($user)
            ->patchJson("/v1/wishlists/{$note->id}", [
                'title' => 'Название',
                'hideSelections' => true,
                'items' => [['label' => 'Хлеб']],
            ])
            ->assertOk()
            ->assertJsonPath('data.title', null)
            ->assertJsonPath('data.hideSelections', false)
            ->assertJsonPath('data.items', []);

        $this->assertDatabaseCount('wishlist_items', 0);
    }

    public function test_update_ignores_content_of_list(): void
    {
        $user = User::factory()->create();
        $wishlist = Wishlist::create(['user_id' => $user->id, 'title' => 'День рождения']);

        $this->actingAs($user)
            ->patchJson("/v1/wishlists/{$wishlist->id}", ['content' => 'Текст'])
            ->assertOk()
            ->assertJsonPath('data.content', null);
    }

    public function test_note_is_not_available_by_shared_link(): void
    {
        $note = $this->createNote(User::factory()->create());

        $this->getJson("/api/v1/shared-wishlists/{$note->id}")
            ->assertNotFound();
    }
}
