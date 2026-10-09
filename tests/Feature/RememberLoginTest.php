<?php

namespace Tests\Feature;

use App\Mail\PasswordResetCodeMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * «Запомнить меня» при входе: POST /v1/login с remember.
 *
 * Cookie remember_web_* восстанавливает вход после истечения сессии.
 * Смена и сброс пароля, выход на всех устройствах и удаление аккаунта
 * делают недействительными cookie других устройств.
 */
class RememberLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Без этого JSON-запросы теста не передают cookie из withCookie()
        $this->withCredentials();
    }

    private function login(User $user, array $overrides = []): TestResponse
    {
        return $this->postJson('/v1/login', [
            'email' => $user->email,
            'password' => 'password',
            ...$overrides,
        ]);
    }

    private function recallerName(): string
    {
        return Auth::guard('web')->getRecallerName();
    }

    // Входит с «Запомнить меня» и возвращает значение cookie
    private function rememberedCookie(User $user): string
    {
        $cookie = $this->login($user, ['remember' => true])
            ->assertOk()
            ->getCookie($this->recallerName());

        $this->assertNotNull($cookie);

        return $cookie->getValue();
    }

    // Сбрасывает аутентификацию, сессию и поставленные в очередь cookie,
    // оставшиеся от предыдущих запросов теста: в тестах они общие для всех запросов
    private function resetDevice(): void
    {
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->app['cookie']->flushQueuedCookies();
    }

    // Запрос с одной cookie «Запомнить меня», без сессии
    private function userWithRememberCookie(string $cookie): TestResponse
    {
        $this->resetDevice();

        return $this->withCookie($this->recallerName(), $cookie)->getJson('/v1/user');
    }

    public function test_remember_cookie_is_set_only_when_requested(): void
    {
        $user = User::factory()->create();

        // Сначала вход без флага: поставленные в очередь cookie в тестах
        // остаются в ответах последующих запросов
        $this->login($user)
            ->assertOk()
            ->assertCookieMissing($this->recallerName());

        $this->app['auth']->forgetGuards();
        $this->flushSession();

        $cookie = $this->login($user, ['remember' => true])
            ->assertOk()
            ->assertCookie($this->recallerName())
            ->getCookie($this->recallerName(), false);

        // Cookie живёт 30 дней (AUTH_REMEMBER_DAYS)
        $this->assertEqualsWithDelta(
            now()->addDays(30)->getTimestamp(),
            $cookie->getExpiresTime(),
            60
        );
    }

    public function test_remember_cookie_restores_login_without_session(): void
    {
        $user = User::factory()->create();

        $cookie = $this->rememberedCookie($user);

        $this->userWithRememberCookie($cookie)
            ->assertOk()
            ->assertJsonPath('data.id', $user->getKey());
    }

    public function test_remember_cookie_is_renewed_on_visit(): void
    {
        $user = User::factory()->create();

        $cookie = $this->rememberedCookie($user);

        // Через 20 дней сессия давно истекла, вход восстанавливается по cookie
        // и cookie выдаётся заново на полные 30 дней
        $this->travel(20)->days();

        $renewed = $this->userWithRememberCookie($cookie)
            ->assertOk()
            ->getCookie($this->recallerName());

        $this->assertNotNull($renewed);
        $this->assertSame($cookie, $renewed->getValue());
        $this->assertEqualsWithDelta(
            now()->addDays(30)->getTimestamp(),
            $renewed->getExpiresTime(),
            60
        );

        // Ещё через 20 дней (40 от входа) браузер ещё хранит продлённую cookie,
        // и по ней вход восстанавливается
        $this->travel(20)->days();

        $this->userWithRememberCookie($renewed->getValue())
            ->assertOk()
            ->assertJsonPath('data.id', $user->getKey());
    }

    public function test_remember_cookie_is_renewed_at_most_once_a_day(): void
    {
        $user = User::factory()->create();

        $cookie = $this->rememberedCookie($user);

        $this->userWithRememberCookie($cookie)
            ->assertOk()
            ->assertCookie($this->recallerName());

        // Та же сессия в тот же день: cookie не выдаётся повторно
        $this->app['cookie']->flushQueuedCookies();

        $this->withCookie($this->recallerName(), $cookie)
            ->getJson('/v1/user')
            ->assertOk()
            ->assertCookieMissing($this->recallerName());

        // Через сутки в той же сессии cookie продлевается снова
        $this->travel(25)->hours();
        $this->app['cookie']->flushQueuedCookies();

        $this->withCookie($this->recallerName(), $cookie)
            ->getJson('/v1/user')
            ->assertOk()
            ->assertCookie($this->recallerName());
    }

    public function test_remember_cookie_is_not_issued_without_remember(): void
    {
        $user = User::factory()->create();

        $this->login($user)->assertOk();

        $this->getJson('/v1/user')
            ->assertOk()
            ->assertCookieMissing($this->recallerName());
    }

    public function test_revoked_remember_cookie_is_not_renewed(): void
    {
        $user = User::factory()->create();

        $oldCookie = $this->rememberedCookie($user);

        // Токен сменился (например, «Выйти везде» на другом устройстве),
        // а у этого устройства ещё жива сессия, открытая после этого без галочки
        $user->setRememberToken('new-token');
        $user->save();

        $this->resetDevice();
        $this->login($user)->assertOk();

        $this->withCookie($this->recallerName(), $oldCookie)
            ->getJson('/v1/user')
            ->assertOk()
            ->assertCookieMissing($this->recallerName());
    }

    public function test_remember_must_be_boolean(): void
    {
        $user = User::factory()->create();

        $this->login($user, ['remember' => 'yes'])
            ->assertStatus(422);
    }

    public function test_logout_signs_out_only_current_device(): void
    {
        $user = User::factory()->create();

        $otherDeviceCookie = $this->rememberedCookie($user);

        $this->app['auth']->forgetGuards();
        $this->flushSession();

        $currentDeviceCookie = $this->rememberedCookie($user);

        // Ответ на выход удаляет cookie «Запомнить меня» текущего устройства
        $forgotten = $this->withCookie($this->recallerName(), $currentDeviceCookie)
            ->postJson('/v1/logout')
            ->assertOk()
            ->getCookie($this->recallerName(), false);

        $this->assertNotNull($forgotten);
        $this->assertTrue($forgotten->isCleared());

        // Вход на другом устройстве сохраняется
        $this->userWithRememberCookie($otherDeviceCookie)
            ->assertOk()
            ->assertJsonPath('data.id', $user->getKey());
    }

    public function test_logout_all_devices_signs_out_everywhere(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $otherDeviceCookie = $this->rememberedCookie($user);

        $this->app['auth']->forgetGuards();
        $this->flushSession();

        $currentDeviceCookie = $this->rememberedCookie($user);

        DB::table('sessions')->insert([
            ['id' => 'user-session', 'user_id' => $user->getKey(), 'payload' => '', 'last_activity' => now()->getTimestamp()],
            ['id' => 'other-user-session', 'user_id' => $otherUser->getKey(), 'payload' => '', 'last_activity' => now()->getTimestamp()],
        ]);
        $user->createToken('device');

        $forgotten = $this->withCookie($this->recallerName(), $currentDeviceCookie)
            ->postJson('/v1/logout-all')
            ->assertOk()
            ->getCookie($this->recallerName(), false);

        $this->assertNotNull($forgotten);
        $this->assertTrue($forgotten->isCleared());

        $this->assertDatabaseMissing('sessions', ['id' => 'user-session']);
        // Сессии других пользователей не затрагиваются
        $this->assertDatabaseHas('sessions', ['id' => 'other-user-session']);
        $this->assertSame(0, $user->tokens()->count());

        $this->userWithRememberCookie($otherDeviceCookie)->assertStatus(401);
        $this->userWithRememberCookie($currentDeviceCookie)->assertStatus(401);
    }

    public function test_logout_all_devices_requires_auth(): void
    {
        $this->postJson('/v1/logout-all')->assertStatus(401);
    }

    public function test_account_deletion_invalidates_remember_cookie(): void
    {
        $user = User::factory()->create();

        $otherDeviceCookie = $this->rememberedCookie($user);

        $this->deleteJson('/v1/user')->assertOk();

        $this->userWithRememberCookie($otherDeviceCookie)->assertStatus(401);
    }

    public function test_password_reset_invalidates_remember_cookie(): void
    {
        $user = User::factory()->create();

        $cookie = $this->rememberedCookie($user);

        $this->app['auth']->forgetGuards();

        Mail::fake();

        $this->postJson('/v1/forgot-password', ['email' => $user->email])->assertOk();

        $code = null;

        Mail::assertQueued(PasswordResetCodeMail::class, function (PasswordResetCodeMail $mail) use (&$code): bool {
            $code = $mail->code;

            return true;
        });

        $this->postJson('/v1/reset-password', [
            'email' => $user->email,
            'code' => $code,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertOk();

        $this->userWithRememberCookie($cookie)->assertStatus(401);
    }

    public function test_password_change_invalidates_other_devices_and_renews_current_cookie(): void
    {
        Mail::fake();

        $user = User::factory()->create();

        $otherDeviceCookie = $this->rememberedCookie($user);

        $this->app['auth']->forgetGuards();

        $currentDeviceCookie = $this->rememberedCookie($user);

        $newCookie = $this->withCookie($this->recallerName(), $currentDeviceCookie)
            ->putJson('/v1/user/password', [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertOk()
            ->getCookie($this->recallerName());

        $this->assertNotNull($newCookie);

        $this->userWithRememberCookie($otherDeviceCookie)->assertStatus(401);

        $this->userWithRememberCookie($newCookie->getValue())
            ->assertOk()
            ->assertJsonPath('data.id', $user->getKey());
    }
}
