<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Проверка именованных лимитеров из AppServiceProvider::configureRateLimiting().
 *
 * Основная цель — убедиться, что у групп маршрутов раздельные счётчики:
 * запросы к одной группе не расходуют лимит другой.
 */
class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    private function createWishlist(): Wishlist
    {
        $owner = User::factory()->create();

        return Wishlist::create([
            'user_id' => $owner->id,
            'title' => 'День рождения',
        ]);
    }

    private function failedLogin(string $email): TestResponse
    {
        return $this->postJson('/v1/login', [
            'email' => $email,
            'password' => 'wrong-password',
        ]);
    }

    // Запрос прошёл лимитер; итоговый статус (401, 422 и т. д.) здесь не важен
    private function assertNotThrottled(TestResponse $response): void
    {
        $this->assertNotSame(429, $response->getStatusCode());
    }

    public function test_login_is_limited_per_email_and_ip(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->failedLogin('victim@example.com')->assertStatus(401);
        }

        $this->failedLogin('victim@example.com')->assertStatus(429);

        // Другой аккаунт с того же IP не заблокирован
        $this->failedLogin('other@example.com')->assertStatus(401);
    }

    public function test_login_is_limited_per_ip_across_accounts(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->failedLogin("user{$i}@example.com")->assertStatus(401);
        }

        $this->failedLogin('user20@example.com')->assertStatus(429);
    }

    public function test_failed_logins_do_not_block_shared_wishlist(): void
    {
        $wishlist = $this->createWishlist();

        for ($i = 0; $i < 6; $i++) {
            $this->failedLogin('victim@example.com');
        }

        $this->failedLogin('victim@example.com')->assertStatus(429);

        $this->getJson("/api/v1/shared-wishlists/{$wishlist->id}")->assertOk();
    }

    public function test_dashboard_requests_do_not_block_shared_wishlist(): void
    {
        $wishlist = $this->createWishlist();
        $user = User::factory()->create();

        $this->actingAs($user);

        for ($i = 0; $i < 7; $i++) {
            $this->getJson('/v1/wishlists')->assertOk();
        }

        $this->getJson("/api/v1/shared-wishlists/{$wishlist->id}")->assertOk();
    }

    public function test_shared_wishlist_read_allows_sixty_requests_per_minute(): void
    {
        $wishlist = $this->createWishlist();

        for ($i = 0; $i < 60; $i++) {
            $this->getJson("/api/v1/shared-wishlists/{$wishlist->id}")->assertOk();
        }

        $this->getJson("/api/v1/shared-wishlists/{$wishlist->id}")->assertStatus(429);
    }

    public function test_shared_wishlist_write_is_limited_separately_from_read(): void
    {
        $wishlist = $this->createWishlist();

        // Пустой payload отклоняется валидацией, но запрос всё равно учитывается
        for ($i = 0; $i < 10; $i++) {
            $this->assertNotThrottled($this->patchJson("/api/v1/shared-wishlists/{$wishlist->id}/items", []));
        }

        $this->patchJson("/api/v1/shared-wishlists/{$wishlist->id}/items", [])->assertStatus(429);

        $this->getJson("/api/v1/shared-wishlists/{$wishlist->id}")->assertOk();
    }

    public function test_verification_code_is_limited_per_email_regardless_of_ip(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->assertNotThrottled(
                $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.{$i}"])
                    ->postJson('/v1/verify-registration', ['email' => $user->email, 'code' => '000000'])
            );
        }

        // Смена IP не обходит лимит для того же email
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.99'])
            ->postJson('/v1/verify-registration', ['email' => $user->email, 'code' => '000000'])
            ->assertStatus(429);
    }

    public function test_registration_is_limited_per_ip(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->assertNotThrottled($this->postJson('/v1/register', []));
        }

        $this->postJson('/v1/register', [])->assertStatus(429);
    }
}
