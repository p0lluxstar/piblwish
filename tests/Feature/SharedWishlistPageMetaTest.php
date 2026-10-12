<?php

namespace Tests\Feature;

use App\Enums\WishlistType;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Мета-теги общей страницы списка (превью ссылки в мессенджерах).
 *
 * Открытый по ссылке список подставляет в HTML своё название и описание,
 * закрытый, архивный и несуществующий — только общие теги сайта.
 */
class SharedWishlistPageMetaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function createGiftList(array $attributes = []): Wishlist
    {
        $user = User::factory()->create(['username' => 'ivan']);

        $wishlist = Wishlist::create([
            'user_id' => $user->id,
            'type' => WishlistType::Gift,
            'title' => 'День рождения',
            ...$attributes,
        ]);

        foreach (['Книга', 'Плед', 'Кружка'] as $position => $label) {
            $wishlist->items()->create(['description' => $label, 'position' => $position]);
        }

        return $wishlist;
    }

    public function test_shared_gift_list_page_has_list_meta(): void
    {
        $wishlist = $this->createGiftList(['due_date' => '2026-10-14']);

        $this->get("/shared-wishlist/{$wishlist->id}")
            ->assertOk()
            ->assertSee('<meta property="og:title" content="День рождения">', false)
            ->assertSee('Список желаний ivan: 3 желания. Дата события: 14 октября 2026.', false)
            ->assertSee('<title>День рождения — PiblKit</title>', false)
            ->assertSee('<meta name="robots" content="noindex">', false);
    }

    public function test_todo_list_meta_shows_progress(): void
    {
        $user = User::factory()->create(['username' => 'ivan']);
        $wishlist = Wishlist::create([
            'user_id' => $user->id,
            'type' => WishlistType::Todo,
            'title' => 'Переезд',
            'is_shared' => true,
        ]);
        $wishlist->items()->create(['description' => 'Коробки', 'position' => 0, 'is_selected' => true]);
        $wishlist->items()->create(['description' => 'Грузчики', 'position' => 1]);

        $this->get("/shared-todolist/{$wishlist->id}")
            ->assertOk()
            ->assertSee('Список дел ivan: выполнено 1 из 2.', false);
    }

    public function test_old_link_also_gets_list_meta(): void
    {
        $wishlist = $this->createGiftList();

        $this->get("/shared/{$wishlist->id}")
            ->assertOk()
            ->assertSee('<meta property="og:title" content="День рождения">', false);
    }

    public function test_closed_list_page_has_only_site_meta(): void
    {
        $hidden = $this->createGiftList(['is_shared' => false]);

        $this->get("/shared-wishlist/{$hidden->id}")
            ->assertOk()
            ->assertDontSee('День рождения')
            ->assertSee('<meta property="og:title" content="PiblKit — желания, дела, сборы и заметки в одном месте">', false);
    }

    public function test_archived_list_page_has_only_site_meta(): void
    {
        $archived = $this->createGiftList(['archived_at' => now()]);

        $this->get("/shared-wishlist/{$archived->id}")
            ->assertOk()
            ->assertDontSee('День рождения');
    }

    public function test_missing_list_page_has_only_site_meta(): void
    {
        $this->get('/shared-wishlist/01JAAAAAAAAAAAAAAAAAAAAAAA')
            ->assertOk()
            ->assertSee('<meta property="og:title" content="PiblKit — желания, дела, сборы и заметки в одном месте">', false);
    }

    public function test_landing_page_is_indexable(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('<meta property="og:image"', false)
            ->assertDontSee('noindex');
    }
}
