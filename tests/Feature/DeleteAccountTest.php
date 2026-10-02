<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Удаление аккаунта: DELETE /v1/user.
 *
 * Пользователь не удаляется физически, а деактивируется через deactivated_at;
 * его вишлисты и их позиции удаляются.
 */
class DeleteAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_is_deactivated_and_wishlists_are_deleted(): void
    {
        $user = User::factory()->create();

        $wishlist = Wishlist::create([
            'user_id' => $user->id,
            'title' => 'День рождения',
        ]);
        $wishlist->items()->create(['description' => 'Книга']);

        // Чужой вишлист не должен пострадать
        $otherWishlist = Wishlist::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Новый год',
        ]);

        $this->actingAs($user)
            ->deleteJson('/v1/user')
            ->assertOk()
            ->assertJsonPath('data.message', 'Аккаунт удалён');

        $this->assertNotNull($user->fresh()->deactivated_at);
        $this->assertDatabaseMissing('wishlists', ['id' => $wishlist->id]);
        $this->assertDatabaseMissing('wishlist_items', ['wishlist_id' => $wishlist->id]);
        $this->assertDatabaseHas('wishlists', ['id' => $otherWishlist->id]);
        $this->assertGuest('web');
    }

    public function test_deactivated_user_cannot_login(): void
    {
        $user = User::factory()->create([
            'deactivated_at' => now(),
        ]);

        $this->postJson('/v1/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertStatus(401);

        $this->assertGuest('web');
    }

    public function test_unverified_user_cannot_login(): void
    {
        $user = User::factory()->unverified()->create();

        $this->postJson('/v1/login', [
            'email' => $user->email,
            'password' => 'password',
        ])
            ->assertStatus(403)
            ->assertJsonPath('data.message', 'Аккаунт не подтверждён. Подтвердите email.');

        $this->assertGuest('web');
    }

    public function test_active_user_can_login(): void
    {
        $user = User::factory()->create();

        $this->postJson('/v1/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk();

        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_guest_cannot_delete_account(): void
    {
        $this->deleteJson('/v1/user')->assertStatus(401);
    }
}
