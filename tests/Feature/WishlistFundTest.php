<?php

namespace Tests\Feature;

use App\Enums\WishlistType;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Сбор: тип fund в /v1/wishlists.
 *
 * Позиции сбора — цели: у каждой ровно одна ссылка https на разрешённую платформу
 * (config('wishlist.fund_hosts')) и необязательная целевая сумма в price
 * до 100 000 000 ₽. Приоритета и режима сюрприза нет. По ссылке сбор открывается
 * только для просмотра: гости переходят на платформу, а выбор и брони отвечают 404.
 */
class WishlistFundTest extends TestCase
{
    use RefreshDatabase;

    private const TBANK_URL = 'https://www.tbank.ru/cf/AbCdEf123';

    private function createFund(User $user, array $attributes = []): Wishlist
    {
        $wishlist = Wishlist::create([
            'user_id' => $user->id,
            'type' => WishlistType::Fund,
            'title' => 'Мои цели',
        ] + $attributes);

        $wishlist->items()->create([
            'description' => 'На дом',
            'urls' => [self::TBANK_URL],
            'price' => 5_000_000,
            'position' => 0,
        ]);

        return $wishlist;
    }

    private function assertFirstError(TestResponse $response, string $key, string $message): void
    {
        $this->assertSame($message, $response->json('data.errors')[$key][0] ?? null);
    }

    private function fundPayload(array $items): array
    {
        return ['type' => 'fund', 'title' => 'Мои цели', 'items' => $items];
    }

    public function test_fund_is_created_shared_by_default(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/v1/wishlists', $this->fundPayload([
                ['label' => 'На дом', 'urls' => [self::TBANK_URL], 'price' => 50_000_000],
                ['label' => 'На машину', 'urls' => ['https://yoomoney.ru/fundraise/XyZ']],
            ]))
            ->assertOk()
            ->assertJsonPath('data.type', 'fund')
            ->assertJsonPath('data.isShared', true)
            ->assertJsonPath('data.hideSelections', false)
            ->assertJsonPath('data.items.0.label', 'На дом')
            ->assertJsonPath('data.items.0.urls', [self::TBANK_URL])
            ->assertJsonPath('data.items.0.price', 50_000_000)
            ->assertJsonPath('data.items.1.price', null);

        $this->assertDatabaseHas('wishlists', [
            'id' => $response->json('data.id'),
            'type' => 'fund',
            'is_shared' => true,
            'hide_selections' => false,
        ]);
    }

    public function test_fund_can_be_created_private(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/v1/wishlists', $this->fundPayload([
                ['label' => 'На дом', 'urls' => [self::TBANK_URL]],
            ]) + ['isShared' => false])
            ->assertOk()
            ->assertJsonPath('data.isShared', false);
    }

    public function test_fund_item_requires_url(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/v1/wishlists', $this->fundPayload([['label' => 'На дом']]))
            ->assertUnprocessable()
            ->assertJsonPath('data.errors', ['items.0.urls' => ['Укажите ссылку на сбор']]);

        $this->actingAs($user)
            ->postJson('/v1/wishlists', $this->fundPayload([['label' => 'На дом', 'urls' => ['']]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.urls.0'], 'data.errors');

        $this->assertDatabaseCount('wishlists', 0);
    }

    public function test_fund_item_accepts_only_one_url(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/v1/wishlists', $this->fundPayload([
                ['label' => 'На дом', 'urls' => [self::TBANK_URL, 'https://yoomoney.ru/fundraise/XyZ']],
            ]))
            ->assertUnprocessable()
            ->tap(fn ($response) => $this->assertFirstError($response, 'items.0.urls', 'У цели сбора может быть только одна ссылка'));
    }

    public static function allowedUrls(): array
    {
        return [
            'Т-Банк' => ['https://www.tbank.ru/cf/AbCdEf123'],
            'Тинькофф' => ['https://www.tinkoff.ru/cf/AbCdEf123'],
            'ЮMoney' => ['https://yoomoney.ru/fundraise/XyZ'],
            'поддомен CloudTips' => ['https://pay.cloudtips.ru/p/1a2b3c'],
            'домен в верхнем регистре' => ['https://YOOMONEY.RU/fundraise/XyZ'],
        ];
    }

    #[DataProvider('allowedUrls')]
    public function test_fund_url_from_allowed_platform_is_accepted(string $url): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/v1/wishlists', $this->fundPayload([['label' => 'На дом', 'urls' => [$url]]]))
            ->assertOk();
    }

    public static function rejectedUrls(): array
    {
        return [
            'чужой домен' => ['https://example.com/cf/AbCdEf123'],
            'домен с разрешённым окончанием' => ['https://evilcloudtips.ru/p/1a2b3c'],
            'разрешённый домен как поддомен чужого' => ['https://tbank.ru.example.com/cf/AbCdEf123'],
            'разрешённый домен в userinfo' => ['https://tbank.ru@example.com/cf/AbCdEf123'],
            'сокращённая ссылка' => ['https://clck.ru/3AbCd'],
            'http без шифрования' => ['http://www.tbank.ru/cf/AbCdEf123'],
            'не ссылка' => ['tbank.ru/cf/AbCdEf123'],
            'javascript' => ['javascript://tbank.ru/%0Aalert(1)'],
        ];
    }

    #[DataProvider('rejectedUrls')]
    public function test_fund_url_from_other_site_is_rejected(string $url): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/v1/wishlists', $this->fundPayload([['label' => 'На дом', 'urls' => [$url]]]))
            ->assertUnprocessable()
            ->tap(fn ($response) => $this->assertFirstError($response, 'items.0.urls.0', 'Ссылки на сбор принимаются только с сайтов: Т-Банк, ЮMoney, CloudTips'));

        $this->assertDatabaseCount('wishlists', 0);
    }

    public function test_fund_target_can_exceed_gift_price_limit(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/v1/wishlists', $this->fundPayload([
                ['label' => 'На дом', 'urls' => [self::TBANK_URL], 'price' => 100_000_000],
            ]))
            ->assertOk()
            ->assertJsonPath('data.items.0.price', 100_000_000);

        $this->actingAs($user)
            ->postJson('/v1/wishlists', $this->fundPayload([
                ['label' => 'На дом', 'urls' => [self::TBANK_URL], 'price' => 100_000_001],
            ]))
            ->assertUnprocessable()
            ->tap(fn ($response) => $this->assertFirstError($response, 'items.0.price', 'Целевая сумма не может превышать 100 000 000 ₽'));

        // Граница стоимости подарка не меняется
        $this->actingAs($user)
            ->postJson('/v1/wishlists', [
                'title' => 'День рождения',
                'items' => [['label' => 'Книга', 'price' => 10_000_001]],
            ])
            ->assertUnprocessable()
            ->tap(fn ($response) => $this->assertFirstError($response, 'items.0.price', 'Стоимость не может превышать 10 000 000 ₽'));
    }

    public function test_fund_rejects_priority_and_surprise_mode(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/v1/wishlists', $this->fundPayload([
                ['label' => 'На дом', 'urls' => [self::TBANK_URL], 'priority' => 3],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.priority'], 'data.errors');

        $this->actingAs($user)
            ->postJson('/v1/wishlists', $this->fundPayload([
                ['label' => 'На дом', 'urls' => [self::TBANK_URL]],
            ]) + ['hideSelections' => true])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['hideSelections'], 'data.errors');

        $this->actingAs($user)
            ->postJson('/v1/wishlists', $this->fundPayload([
                ['label' => 'На дом', 'urls' => [self::TBANK_URL]],
            ]) + ['guestsCanCheck' => true])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['guestsCanCheck'], 'data.errors');
    }

    public function test_fund_is_updated(): void
    {
        $user = User::factory()->create();
        $wishlist = $this->createFund($user);
        $item = $wishlist->items()->first();

        $this->actingAs($user)
            ->patchJson("/v1/wishlists/{$wishlist->id}", [
                'dueDate' => '2030-06-01',
                'items' => [
                    ['id' => $item->id, 'label' => 'На дом у моря', 'urls' => ['https://pay.cloudtips.ru/p/1a2b3c'], 'price' => 80_000_000],
                    ['label' => 'На машину', 'urls' => [self::TBANK_URL]],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.dueDate', '2030-06-01')
            ->assertJsonPath('data.items.0.id', $item->id)
            ->assertJsonPath('data.items.0.label', 'На дом у моря')
            ->assertJsonPath('data.items.0.urls', ['https://pay.cloudtips.ru/p/1a2b3c'])
            ->assertJsonPath('data.items.0.price', 80_000_000)
            ->assertJsonPath('data.items.1.label', 'На машину');
    }

    public function test_fund_url_is_checked_on_update(): void
    {
        $user = User::factory()->create();
        $wishlist = $this->createFund($user);

        $this->actingAs($user)
            ->patchJson("/v1/wishlists/{$wishlist->id}", [
                'items' => [['label' => 'На дом', 'urls' => ['https://example.com/cf/AbCdEf123']]],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.urls.0'], 'data.errors');

        $this->actingAs($user)
            ->patchJson("/v1/wishlists/{$wishlist->id}", [
                'items' => [['label' => 'На дом']],
            ])
            ->assertUnprocessable()
            ->tap(fn ($response) => $this->assertFirstError($response, 'items.0.urls', 'Укажите ссылку на сбор'));

        $this->assertSame([self::TBANK_URL], $wishlist->items()->first()->urls);
    }

    public function test_fund_ignores_priority_and_surprise_mode_on_update(): void
    {
        $user = User::factory()->create();
        $wishlist = $this->createFund($user);

        $this->actingAs($user)
            ->patchJson("/v1/wishlists/{$wishlist->id}", [
                'hideSelections' => true,
                'items' => [['label' => 'На дом', 'urls' => [self::TBANK_URL], 'priority' => 3]],
            ])
            ->assertOk()
            ->assertJsonPath('data.hideSelections', false)
            ->assertJsonPath('data.items.0.priority', null);
    }

    public function test_gift_list_still_accepts_any_http_url(): void
    {
        $user = User::factory()->create();
        $wishlist = Wishlist::create(['user_id' => $user->id, 'title' => 'День рождения']);

        $this->actingAs($user)
            ->patchJson("/v1/wishlists/{$wishlist->id}", [
                'items' => [['label' => 'Книга', 'urls' => ['http://example.com/book']]],
            ])
            ->assertOk()
            ->assertJsonPath('data.items.0.urls', ['http://example.com/book']);
    }

    public function test_shared_fund_is_shown_to_guest(): void
    {
        $wishlist = $this->createFund(User::factory()->create());

        $response = $this->getJson("/api/v1/shared-wishlists/{$wishlist->id}")
            ->assertOk()
            ->assertJsonPath('data.type', 'fund')
            ->assertJsonPath('data.items.0.label', 'На дом')
            ->assertJsonPath('data.items.0.urls', [self::TBANK_URL])
            ->assertJsonPath('data.items.0.price', 5_000_000);

        $this->assertArrayNotHasKey('jointGift', $response->json('data.items.0'));
    }

    public function test_private_fund_is_not_shown_to_guest(): void
    {
        $wishlist = $this->createFund(User::factory()->create(), ['is_shared' => false]);

        $this->getJson("/api/v1/shared-wishlists/{$wishlist->id}")->assertNotFound();
    }

    public function test_guest_cannot_select_fund_items(): void
    {
        $wishlist = $this->createFund(User::factory()->create());
        $item = $wishlist->items()->first();

        $this->patchJson("/api/v1/shared-wishlists/{$wishlist->id}/items", [
            'item_ids' => [$item->id],
        ])->assertNotFound();

        $this->postJson("/api/v1/shared-wishlists/{$wishlist->id}/items/check", [
            'item_ids' => [$item->id],
            'name' => 'Аня',
        ])->assertNotFound();

        $this->putJson("/api/v1/shared-wishlists/{$wishlist->id}/items/{$item->id}/joint-gift", [
            'token' => Str::random(40),
            'joint_gift' => null,
        ])->assertNotFound();

        $this->assertFalse($item->fresh()->is_selected);
        $this->assertDatabaseCount('wishlist_reservations', 0);
    }

    public function test_owner_cannot_select_fund_items(): void
    {
        $user = User::factory()->create();
        $wishlist = $this->createFund($user);
        $item = $wishlist->items()->first();

        $this->actingAs($user)
            ->patchJson("/v1/wishlists/{$wishlist->id}/items/{$item->id}", ['isSelected' => true])
            ->assertUnprocessable();

        $this->actingAs($user)
            ->patchJson("/v1/wishlists/{$wishlist->id}", [
                'items' => [['id' => $item->id, 'label' => 'На дом', 'urls' => [self::TBANK_URL], 'isSelected' => true]],
            ])
            ->assertOk();

        $this->assertFalse($item->fresh()->is_selected);
    }

    public function test_fund_can_be_saved_by_other_user(): void
    {
        $wishlist = $this->createFund(User::factory()->create());

        $this->actingAs(User::factory()->create())
            ->postJson("/v1/saved-wishlists/{$wishlist->id}")
            ->assertSuccessful();

        $this->getJson('/v1/saved-wishlists')
            ->assertOk()
            ->assertJsonPath('data.0.type', 'fund')
            ->assertJsonPath('data.0.available', true)
            ->assertJsonPath('data.0.itemsCount', 1);
    }

    public function test_archived_fund_is_not_shown_to_guest(): void
    {
        $user = User::factory()->create();
        $wishlist = $this->createFund($user);

        $this->actingAs($user)
            ->postJson("/v1/wishlists/{$wishlist->id}/archive")
            ->assertOk();

        $this->getJson("/api/v1/shared-wishlists/{$wishlist->id}")->assertNotFound();
    }
}
