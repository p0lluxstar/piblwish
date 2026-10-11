<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Фотография пользователя: POST и DELETE /v1/user/avatar, поле avatarUrl.
 *
 * Файл перекодируется в WebP 256×256 и хранится на диске public
 * в avatars/{id пользователя}/{ULID}.webp; в users.avatar_path — путь к нему.
 */
class UserAvatarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    private function uploadAvatar(User $user, UploadedFile $file): TestResponse
    {
        return $this->actingAs($user)
            ->post('/v1/user/avatar', ['avatar' => $file], ['Accept' => 'application/json']);
    }

    public function test_new_user_has_no_avatar(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/v1/user')
            ->assertOk()
            ->assertJsonPath('data.avatarUrl', null);
    }

    public function test_avatar_is_converted_to_square_webp(): void
    {
        $user = User::factory()->create();

        $response = $this->uploadAvatar($user, UploadedFile::fake()->image('photo.jpg', 1200, 800))
            ->assertOk();

        $path = $user->fresh()->avatar_path;

        $this->assertMatchesRegularExpression(
            '#^avatars/'.$user->id.'/[0-9a-z]{26}\.webp$#',
            $path
        );
        Storage::disk('public')->assertExists($path);
        $response->assertJsonPath('data.avatarUrl', Storage::disk('public')->url($path));

        [$width, $height, $type] = getimagesizefromstring(Storage::disk('public')->get($path));

        $this->assertSame([256, 256, IMAGETYPE_WEBP], [$width, $height, $type]);
    }

    public function test_new_avatar_replaces_old_file(): void
    {
        $user = User::factory()->create();

        $this->uploadAvatar($user, UploadedFile::fake()->image('first.png', 300, 300))->assertOk();
        $oldPath = $user->fresh()->avatar_path;

        $this->uploadAvatar($user, UploadedFile::fake()->image('second.gif', 400, 400))->assertOk();
        $newPath = $user->fresh()->avatar_path;

        $this->assertNotSame($oldPath, $newPath);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($newPath);
    }

    public function test_avatar_is_required(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/v1/user/avatar')
            ->assertUnprocessable()
            ->assertJsonPath('data.errors.avatar.0', 'Выберите фотографию');
    }

    public function test_non_image_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->uploadAvatar($user, UploadedFile::fake()->create('document.pdf', 10, 'application/pdf'))
            ->assertUnprocessable()
            ->assertJsonPath('data.errors.avatar.0', 'Поддерживаются изображения JPEG, PNG, WebP и GIF');

        $this->assertNull($user->fresh()->avatar_path);
    }

    public function test_svg_is_rejected(): void
    {
        $user = User::factory()->create();
        $svg = UploadedFile::fake()->createWithContent(
            'avatar.svg',
            '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'
        );

        $this->uploadAvatar($user, $svg)->assertUnprocessable();

        $this->assertNull($user->fresh()->avatar_path);
    }

    public function test_too_large_file_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->uploadAvatar($user, UploadedFile::fake()->image('big.jpg', 500, 500)->size(2049))
            ->assertUnprocessable()
            ->assertJsonPath('data.errors.avatar.0', 'Файл должен быть не больше 2 МБ');
    }

    public function test_too_large_dimensions_are_rejected(): void
    {
        $user = User::factory()->create();

        $this->uploadAvatar($user, UploadedFile::fake()->image('wide.png', 4097, 10))
            ->assertUnprocessable()
            ->assertJsonPath('data.errors.avatar.0', 'Изображение должно быть не больше 4096×4096 пикселей');
    }

    public function test_avatar_is_deleted(): void
    {
        $user = User::factory()->create();

        $this->uploadAvatar($user, UploadedFile::fake()->image('photo.jpg', 300, 300))->assertOk();
        $path = $user->fresh()->avatar_path;

        $this->actingAs($user)
            ->deleteJson('/v1/user/avatar')
            ->assertOk()
            ->assertJsonPath('data.avatarUrl', null);

        $this->assertNull($user->fresh()->avatar_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_deleting_missing_avatar_succeeds(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->deleteJson('/v1/user/avatar')
            ->assertOk()
            ->assertJsonPath('data.avatarUrl', null);
    }

    public function test_guest_cannot_change_avatar(): void
    {
        $this->post('/v1/user/avatar', [
            'avatar' => UploadedFile::fake()->image('photo.jpg', 300, 300),
        ], ['Accept' => 'application/json'])->assertUnauthorized();

        $this->deleteJson('/v1/user/avatar')->assertUnauthorized();
    }

    public function test_shared_wishlist_shows_owner_avatar(): void
    {
        $user = User::factory()->create();
        $wishlist = Wishlist::create(['user_id' => $user->id, 'title' => 'День рождения']);

        $this->getJson("/api/v1/shared-wishlists/{$wishlist->id}")
            ->assertOk()
            ->assertJsonPath('data.avatarUrl', null);

        $this->uploadAvatar($user, UploadedFile::fake()->image('photo.jpg', 300, 300))->assertOk();

        $this->getJson("/api/v1/shared-wishlists/{$wishlist->id}")
            ->assertOk()
            ->assertJsonPath('data.avatarUrl', Storage::disk('public')->url($user->fresh()->avatar_path));
    }

    public function test_account_deletion_removes_avatar_files(): void
    {
        $user = User::factory()->create();

        $this->uploadAvatar($user, UploadedFile::fake()->image('photo.jpg', 300, 300))->assertOk();

        // Лишний файл, оставшийся после сбоя, тоже должен быть удалён
        Storage::disk('public')->put("avatars/{$user->id}/orphan.webp", 'x');

        $this->actingAs($user)->deleteJson('/v1/user')->assertOk();

        $this->assertNull($user->fresh()->avatar_path);
        Storage::disk('public')->assertDirectoryEmpty('avatars');
        $this->assertFalse(Storage::disk('public')->directoryExists("avatars/{$user->id}"));
    }
}
