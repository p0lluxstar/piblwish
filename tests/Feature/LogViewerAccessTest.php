<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Доступ к журналам /log-viewer (opcodesio/log-viewer).
 *
 * Страница и её API открыты только пользователям из ADMIN_USER_IDS
 * (config app.admin_user_ids); удалять файлы журнала нельзя никому.
 */
class LogViewerAccessTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        config(['app.admin_user_ids' => [(string) $user->getKey()]]);

        return $user;
    }

    public function test_guest_cannot_open_log_viewer(): void
    {
        $this->get('/log-viewer')->assertForbidden();
        $this->getJson('/log-viewer/api/files')->assertForbidden();
    }

    public function test_regular_user_cannot_open_log_viewer(): void
    {
        $this->admin();
        $user = User::factory()->create();

        $this->actingAs($user)->get('/log-viewer')->assertForbidden();
        $this->actingAs($user)->getJson('/log-viewer/api/files')->assertForbidden();
    }

    public function test_nobody_is_admin_when_list_is_empty(): void
    {
        config(['app.admin_user_ids' => []]);
        $user = User::factory()->create();

        $this->actingAs($user)->get('/log-viewer')->assertForbidden();
    }

    public function test_admin_opens_log_viewer_instead_of_spa(): void
    {
        $admin = $this->admin();

        // Отвечает пакет, а не SPA-маршрут /{any}
        $this->actingAs($admin)->get('/log-viewer')
            ->assertOk()
            ->assertSee('vendor/log-viewer/app.js', false);

        $this->actingAs($admin)->getJson('/log-viewer/api/files')->assertOk();
    }

    public function test_admin_can_download_but_not_delete_log_files(): void
    {
        $admin = $this->admin();
        $gate = Gate::forUser($admin);

        $this->assertTrue($gate->allows('downloadLogFile'));
        $this->assertTrue($gate->allows('downloadLogFolder'));
        $this->assertFalse($gate->allows('deleteLogFile'));
        $this->assertFalse($gate->allows('deleteLogFolder'));
    }

    public function test_regular_user_cannot_download_log_files(): void
    {
        $this->admin();
        $user = User::factory()->create();

        $this->assertFalse(Gate::forUser($user)->allows('downloadLogFile'));
    }
}
