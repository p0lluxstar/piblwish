<?php

namespace Tests\Feature;

use App\Mail\DueDateReminderMail;
use App\Models\User;
use App\Models\Wishlist;
use App\Services\Reminder\DueDateReminderService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Письма-напоминания перед датой списка: команда wishlists:send-due-reminders
 * и отписка по ссылке из письма.
 *
 * Пользователь получает одно письмо о своих списках и сохранённых чужих
 * списках, дата которых наступает в пределах его срока (reminder_days, 1–7 дней,
 * по умолчанию 3). О каждом списке с данной датой напоминание приходит один раз.
 */
class DueDateReminderTest extends TestCase
{
    use RefreshDatabase;

    private const TODAY = '2026-10-11';

    private const TOMORROW = '2026-10-12';

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->travelTo(CarbonImmutable::parse(self::TODAY.' 09:00', 'Europe/Moscow'));
    }

    private function createWishlist(User $owner, array $attributes = []): Wishlist
    {
        return Wishlist::create([
            'user_id' => $owner->id,
            'title' => 'День рождения',
            'due_date' => self::TOMORROW,
            ...$attributes,
        ]);
    }

    private function runReminders(): void
    {
        $this->artisan('wishlists:send-due-reminders')->assertSuccessful();
    }

    public function test_owner_is_reminded_about_list_due_tomorrow(): void
    {
        $user = User::factory()->create(['username' => 'anna']);
        $todo = $this->createWishlist($user, ['type' => 'todo', 'title' => 'Переезд']);
        $todo->items()->create(['description' => 'Коробки', 'position' => 0, 'is_selected' => true]);
        $todo->items()->create(['description' => 'Грузчики', 'position' => 1]);

        $this->runReminders();

        Mail::assertQueued(DueDateReminderMail::class, function (DueDateReminderMail $mail) use ($user) {
            return $mail->hasTo($user->email)
                && $mail->username === 'anna'
                && $mail->reminderDays === '3 дня'
                && $mail->ownLists[0]['date'] === 'Завтра, 12 октября'
                && count($mail->ownLists) === 1
                && $mail->ownLists[0]['title'] === 'Переезд'
                && $mail->ownLists[0]['details'] === 'Не выполнено дел: 1 из 2'
                && $mail->savedLists === [];
        });
    }

    public function test_lists_with_other_dates_and_excluded_lists_are_skipped(): void
    {
        $user = User::factory()->create();
        $this->createWishlist($user, ['due_date' => self::TODAY]);
        // Срок по умолчанию — 3 дня, 15 октября за его пределами
        $this->createWishlist($user, ['due_date' => '2026-10-15']);
        $this->createWishlist($user, ['due_date' => '2026-10-01']);
        $this->createWishlist($user, ['archived_at' => now()]);
        $this->createWishlist($user, ['type' => 'note', 'title' => null, 'content' => 'Текст']);

        // Список дел, в котором всё выполнено
        $done = $this->createWishlist($user, ['type' => 'todo']);
        $done->items()->create(['description' => 'Готово', 'position' => 0, 'is_selected' => true]);

        $this->runReminders();

        Mail::assertNothingQueued();
    }

    public function test_surprise_mode_hides_selection_count_from_owner(): void
    {
        $user = User::factory()->create();
        $gift = $this->createWishlist($user, ['hide_selections' => true]);
        $gift->items()->create(['description' => 'Книга', 'position' => 0, 'is_selected' => true]);

        $this->runReminders();

        Mail::assertQueued(DueDateReminderMail::class, fn (DueDateReminderMail $mail) => $mail->ownLists[0]['details'] === null);
    }

    public function test_user_is_reminded_about_saved_wishlist(): void
    {
        $owner = User::factory()->create(['username' => 'boris']);
        $user = User::factory()->create();
        $gift = $this->createWishlist($owner);
        $gift->items()->create(['description' => 'Книга', 'position' => 0, 'is_selected' => true]);
        $gift->items()->create(['description' => 'Шарф', 'position' => 1]);
        $user->savedWishlists()->create(['wishlist_id' => $gift->id]);

        $this->runReminders();

        Mail::assertQueued(DueDateReminderMail::class, function (DueDateReminderMail $mail) use ($user, $gift) {
            return $mail->hasTo($user->email)
                && $mail->ownLists === []
                && $mail->savedLists[0]['owner'] === 'boris'
                && $mail->savedLists[0]['details'] === 'Свободно позиций: 1 из 2'
                && str_ends_with($mail->savedLists[0]['url'], "/shared-wishlist/{$gift->id}");
        });
        // Владелец получает своё письмо
        Mail::assertQueued(DueDateReminderMail::class, fn (DueDateReminderMail $mail) => $mail->hasTo($owner->email));
        Mail::assertQueuedCount(2);
    }

    public function test_saved_wishlist_closed_by_owner_is_skipped(): void
    {
        $owner = User::factory()->create(['email_reminders' => false]);
        $user = User::factory()->create();
        $gift = $this->createWishlist($owner, ['is_shared' => false]);
        $user->savedWishlists()->create(['wishlist_id' => $gift->id]);

        $this->runReminders();

        Mail::assertNothingQueued();
    }

    // После смены ссылки закладка с прежним токеном недоступна, и напоминания о ней нет
    public function test_saved_wishlist_with_changed_link_is_skipped(): void
    {
        $owner = User::factory()->create(['email_reminders' => false]);
        $user = User::factory()->create();
        $gift = $this->createWishlist($owner);
        $user->savedWishlists()->create(['wishlist_id' => $gift->id]);

        $gift->share_token = Wishlist::newShareToken();
        $gift->save();

        $this->runReminders();

        Mail::assertNothingQueued();
    }

    public function test_disabled_and_unverified_users_are_skipped(): void
    {
        $this->createWishlist(User::factory()->create(['email_reminders' => false]));
        $this->createWishlist(User::factory()->unverified()->create());
        $this->createWishlist(User::factory()->create(['deactivated_at' => now()]));

        $this->runReminders();

        Mail::assertNothingQueued();
    }

    public function test_run_logs_number_of_sent_reminders(): void
    {
        $this->createWishlist(User::factory()->create());
        Log::spy();

        $this->runReminders();
        $this->runReminders();

        Log::shouldHaveReceived('info')->with('Due date reminders sent', ['count' => 1])->once();
        Log::shouldHaveReceived('info')->with('Due date reminders sent', ['count' => 0])->once();
    }

    public function test_repeated_run_does_not_duplicate_reminders(): void
    {
        $user = User::factory()->create();
        $this->createWishlist($user);

        $this->runReminders();
        $this->runReminders();

        Mail::assertQueuedCount(1);
    }

    public function test_moved_due_date_is_reminded_again(): void
    {
        $user = User::factory()->create();
        $wishlist = $this->createWishlist($user);

        $this->runReminders();

        $wishlist->update(['due_date' => '2026-10-13']);
        $this->travelTo(CarbonImmutable::parse('2026-10-12 09:00', 'Europe/Moscow'));
        $this->runReminders();

        Mail::assertQueuedCount(2);
    }

    public function test_tomorrow_is_counted_in_reminders_timezone(): void
    {
        $user = User::factory()->create();
        $this->createWishlist($user);

        // 23:30 по UTC 10 октября — уже 11 октября по Москве
        $this->travelTo(CarbonImmutable::parse('2026-10-10 23:30', 'UTC'));
        $this->runReminders();

        Mail::assertQueuedCount(1);
    }

    public function test_unsubscribe_link_disables_reminders(): void
    {
        $user = User::factory()->create();
        $url = app(DueDateReminderService::class)->unsubscribeUrl($user);

        // Открытие ссылки только показывает кнопку и ничего не меняет
        $this->get($url)
            ->assertOk()
            ->assertSee('Отключить напоминания');
        $this->assertTrue($user->fresh()->email_reminders);

        $this->post($url)
            ->assertOk()
            ->assertSee('Напоминания отключены');
        $this->assertFalse($user->fresh()->email_reminders);
    }

    public function test_unsubscribe_link_requires_valid_signature(): void
    {
        $user = User::factory()->create();

        $this->get("/email-reminders/unsubscribe/{$user->id}")->assertForbidden();
        $this->post("/email-reminders/unsubscribe/{$user->id}?signature=wrong")->assertForbidden();

        $this->assertTrue($user->fresh()->email_reminders);
    }

    public function test_setting_is_changed_from_account_settings(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/v1/user')
            ->assertJsonPath('data.emailReminders', true)
            ->assertJsonPath('data.reminderDays', 3);

        $this->actingAs($user)
            ->patchJson('/v1/user', ['emailReminders' => false])
            ->assertOk()
            ->assertJsonPath('data.emailReminders', false)
            ->assertJsonPath('data.showFriendsEvents', true);

        $this->assertFalse($user->fresh()->email_reminders);
    }

    public function test_reminder_days_setting_is_changed_and_validated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patchJson('/v1/user', ['reminderDays' => 6])
            ->assertOk()
            ->assertJsonPath('data.reminderDays', 6)
            ->assertJsonPath('data.emailReminders', true);

        $this->actingAs($user)->patchJson('/v1/user', ['reminderDays' => 0])->assertUnprocessable();
        $this->actingAs($user)->patchJson('/v1/user', ['reminderDays' => 8])->assertUnprocessable();
        $this->actingAs($user)->patchJson('/v1/user', ['reminderDays' => 'три'])->assertUnprocessable();

        $this->assertSame(6, $user->fresh()->reminder_days);
    }

    public function test_lists_within_reminder_days_are_included(): void
    {
        $user = User::factory()->create(['reminder_days' => 6]);
        $this->createWishlist($user, ['title' => 'Через шесть', 'due_date' => '2026-10-17']);
        $this->createWishlist($user, ['title' => 'Через три', 'due_date' => '2026-10-14']);
        $this->createWishlist($user, ['title' => 'Через семь', 'due_date' => '2026-10-18']);

        $this->runReminders();

        Mail::assertQueued(DueDateReminderMail::class, function (DueDateReminderMail $mail) {
            return $mail->reminderDays === '6 дней'
                && array_column($mail->ownLists, 'title') === ['Через три', 'Через шесть']
                && $mail->ownLists[0]['date'] === 'Через 3 дня, 14 октября'
                && $mail->ownLists[1]['date'] === 'Через 6 дней, 17 октября';
        });
        Mail::assertQueuedCount(1);
    }

    public function test_each_list_is_reminded_once_within_reminder_days(): void
    {
        $user = User::factory()->create(['reminder_days' => 6]);
        $this->createWishlist($user, ['due_date' => '2026-10-17']);

        $this->runReminders();

        // В следующие дни список остаётся в промежутке, но письмо о нём уже было
        $this->travelTo(CarbonImmutable::parse('2026-10-12 09:00', 'Europe/Moscow'));
        $this->runReminders();
        $this->travelTo(CarbonImmutable::parse('2026-10-16 09:00', 'Europe/Moscow'));
        $this->runReminders();

        Mail::assertQueuedCount(1);
    }

    public function test_list_added_inside_reminder_days_is_reminded_next_run(): void
    {
        $user = User::factory()->create(['reminder_days' => 6]);
        $this->createWishlist($user, ['title' => 'Первый', 'due_date' => '2026-10-17']);

        $this->runReminders();

        // Дату второго списка указали, когда до неё оставалось меньше шести дней
        $this->createWishlist($user, ['title' => 'Второй', 'due_date' => '2026-10-14']);
        $this->travelTo(CarbonImmutable::parse('2026-10-12 09:00', 'Europe/Moscow'));
        $this->runReminders();

        Mail::assertQueuedCount(2);
        Mail::assertQueued(DueDateReminderMail::class, fn (DueDateReminderMail $mail) => array_column($mail->ownLists, 'title') === ['Второй']);
    }

    public function test_decreased_reminder_days_still_reminds_about_near_list(): void
    {
        $user = User::factory()->create(['reminder_days' => 2]);
        // Срок был 6 дней, но за 6 дней рассылка не сработала (срок сменили позже)
        $this->createWishlist($user, ['due_date' => '2026-10-13']);

        $this->runReminders();

        Mail::assertQueued(DueDateReminderMail::class, fn (DueDateReminderMail $mail) => $mail->ownLists[0]['date'] === 'Через 2 дня, 13 октября');
    }

    public function test_reminder_days_apply_to_saved_wishlists(): void
    {
        $owner = User::factory()->create(['email_reminders' => false]);
        $user = User::factory()->create(['reminder_days' => 5]);
        $gift = $this->createWishlist($owner, ['due_date' => '2026-10-16']);
        $user->savedWishlists()->create(['wishlist_id' => $gift->id]);

        $this->runReminders();

        Mail::assertQueued(DueDateReminderMail::class, fn (DueDateReminderMail $mail) => $mail->hasTo($user->email)
            && $mail->savedLists[0]['date'] === 'Через 5 дней, 16 октября');
        Mail::assertQueuedCount(1);
    }

    public function test_days_label_uses_russian_plural(): void
    {
        $this->assertSame('1 день', DueDateReminderService::daysLabel(1));
        $this->assertSame('4 дня', DueDateReminderService::daysLabel(4));
        $this->assertSame('7 дней', DueDateReminderService::daysLabel(7));
    }

    public function test_default_reminder_days_is_three(): void
    {
        $user = User::factory()->create();
        $this->createWishlist($user, ['due_date' => '2026-10-14']);

        $this->runReminders();

        Mail::assertQueued(DueDateReminderMail::class, fn (DueDateReminderMail $mail) => $mail->ownLists[0]['date'] === 'Через 3 дня, 14 октября');
    }
}
