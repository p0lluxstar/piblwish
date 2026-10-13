<?php

namespace App\Services\Reminder;

use App\Enums\WishlistType;
use App\Mail\DueDateReminderMail;
use App\Models\User;
use App\Models\Wishlist;
use App\Models\WishlistReminder;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * Письма-напоминания о списках, дата которых скоро наступает.
 *
 * Срок напоминания пользователь задаёт сам (users.reminder_days, от 1 до 7 дней,
 * по умолчанию 3),
 * он один для своих и сохранённых чужих списков. В письмо попадают списки,
 * дата которых лежит в промежутке от завтра до сегодня + срок, а не ровно через
 * срок: иначе список, дату которого указали позже, или список, срок которого
 * пользователь уменьшил, остался бы без напоминания. О каждом списке с данной
 * датой напоминание приходит один раз: отправленные записываются
 * в WishlistReminder, поэтому повторный запуск рассылки писем не дублирует,
 * а после переноса даты владельцем напоминание о новой дате придёт снова.
 *
 * Не напоминается о заметках (у них нет даты), архивных списках, выполненных
 * списках дел и чужих списках, которые больше не открываются по ссылке.
 */
class DueDateReminderService
{
    public const MIN_DAYS = 1;

    public const MAX_DAYS = 7;

    // Срок по умолчанию, совпадает со значением колонки users.reminder_days в БД
    public const DEFAULT_DAYS = 3;

    // Отправить напоминания относительно даты $today; возвращает число отправленных писем
    public function sendReminders(CarbonImmutable $today): int
    {
        $from = $today->addDay()->toDateString();
        $sent = 0;

        // Промежуток дат зависит от срока пользователя, поэтому пользователи
        // выбираются отдельно для каждого срока
        for ($days = self::MIN_DAYS; $days <= self::MAX_DAYS; $days++) {
            $to = $today->addDays($days)->toDateString();

            User::query()
                ->where('is_active', true)
                ->whereNotNull('email_verified_at')
                ->whereNull('deactivated_at')
                ->where('email_reminders', true)
                ->where('reminder_days', $days)
                ->where(function (Builder $query) use ($from, $to): void {
                    $query->whereHas('wishlists', fn (Builder $query) => $this->ownDue($query, $from, $to))
                        ->orWhereHas('savedWishlists', fn (Builder $query) => $query->whereHas(
                            'wishlist',
                            fn (Builder $query) => $this->savedDue($query, $from, $to)
                                ->whereColumn('wishlists.share_token', 'saved_wishlists.share_token')
                        ));
                })
                ->chunkById(100, function ($users) use ($today, $from, $to, &$sent): void {
                    foreach ($users as $user) {
                        if ($this->remindUser($user, $today, $from, $to)) {
                            $sent++;
                        }
                    }
                });
        }

        return $sent;
    }

    // Собрать списки пользователя с датой от $from до $to, о которых он ещё не
    // предупреждён, и отправить письмо; false — напоминать не о чем
    private function remindUser(User $user, CarbonImmutable $today, string $from, string $to): bool
    {
        $own = $user->wishlists()
            ->where(fn (Builder $query) => $this->ownDue($query, $from, $to))
            ->withCount($this->itemCounts())
            ->orderBy('due_date')
            ->get();

        $saved = Wishlist::query()
            // Токен ссылки уникален: после смены ссылки владельцем закладка
            // с прежним токеном списка не находит
            ->whereIn('share_token', $user->savedWishlists()->select('share_token'))
            ->where(fn (Builder $query) => $this->savedDue($query, $from, $to))
            ->with('user')
            ->withCount($this->itemCounts())
            ->orderBy('due_date')
            ->get();

        // Напоминание уже отправлено, если есть запись с тем же списком и той же датой
        $alreadySent = WishlistReminder::query()
            ->where('user_id', $user->id)
            ->whereDate('due_date', '>=', $from)
            ->whereDate('due_date', '<=', $to)
            ->get()
            ->map(fn (WishlistReminder $reminder) => $reminder->wishlist_id.'|'.$reminder->due_date->toDateString())
            ->all();

        $isNew = fn (Wishlist $wishlist) => ! in_array($wishlist->id.'|'.$wishlist->due_date->toDateString(), $alreadySent, true);
        $own = $own->filter($isNew);
        $saved = $saved->filter($isNew);

        if ($own->isEmpty() && $saved->isEmpty()) {
            return false;
        }

        // Записи создаются до отправки: письмо уходит через очередь, и если
        // рассылка запустится ещё раз раньше, чем оно отправлено, повтора не будет
        foreach ($own->concat($saved) as $wishlist) {
            WishlistReminder::query()->insertOrIgnore([
                'user_id' => $user->id,
                'wishlist_id' => $wishlist->id,
                'due_date' => $wishlist->due_date->toDateString(),
                'created_at' => now(),
            ]);
        }

        Mail::to($user->email)->send(new DueDateReminderMail(
            username: $user->username,
            reminderDays: self::daysLabel($user->reminder_days),
            ownLists: $own->map(fn (Wishlist $wishlist) => $this->describeOwn($wishlist, $today))->values()->all(),
            savedLists: $saved->map(fn (Wishlist $wishlist) => $this->describeSaved($wishlist, $today))->values()->all(),
            dashboardUrl: url('/dashboard'),
            unsubscribeUrl: $this->unsubscribeUrl($user),
        ));

        return true;
    }

    // Ссылка отписки из письма; подписана без хоста и схемы, поэтому
    // проверка подписи не зависит от того, как прокси передаёт адрес
    public function unsubscribeUrl(User $user): string
    {
        return url(URL::signedRoute('email-reminders.unsubscribe', ['user' => $user->id], absolute: false));
    }

    // «1 день», «3 дня», «7 дней»
    public static function daysLabel(int $days): string
    {
        $word = match (true) {
            $days % 10 === 1 && $days % 100 !== 11 => 'день',
            in_array($days % 10, [2, 3, 4], true) && ! in_array($days % 100, [12, 13, 14], true) => 'дня',
            default => 'дней',
        };

        return "{$days} {$word}";
    }

    // Свой список с датой от $from до $to, о котором стоит напомнить
    private function ownDue(Builder $query, string $from, string $to): void
    {
        $query->active()
            ->where('type', '!=', WishlistType::Note)
            ->whereDate('due_date', '>=', $from)
            ->whereDate('due_date', '<=', $to)
            ->where(fn (Builder $query) => $this->notCompletedTodo($query));
    }

    // Сохранённый чужой список с датой от $from до $to, который открывается по ссылке
    private function savedDue(Builder $query, string $from, string $to): Builder
    {
        return $query->openByLink()
            ->whereDate('due_date', '>=', $from)
            ->whereDate('due_date', '<=', $to)
            ->where(fn (Builder $query) => $this->notCompletedTodo($query));
    }

    // Список дел, в котором все дела выполнены (или дел нет), не нуждается в напоминании
    private function notCompletedTodo(Builder $query): void
    {
        $query->where('type', '!=', WishlistType::Todo)
            ->orWhereHas('items', fn (Builder $query) => $query->where('is_selected', false));
    }

    private function itemCounts(): array
    {
        return [
            'items',
            'items as selected_items_count' => fn (Builder $query) => $query->where('is_selected', true),
        ];
    }

    // «Завтра, 12 октября», «Через 6 дней, 17 октября»
    private function dateLabel(Wishlist $wishlist, CarbonImmutable $today): string
    {
        // Обе даты разбираются без времени в одном поясе: $today задан в поясе
        // рассылки, а due_date — в поясе приложения
        $dueDate = CarbonImmutable::parse($wishlist->due_date->toDateString());
        $days = (int) CarbonImmutable::parse($today->toDateString())->diffInDays($dueDate);
        $date = $dueDate->locale('ru')->translatedFormat('j F');

        return $days === 1 ? "Завтра, {$date}" : 'Через '.self::daysLabel($days).", {$date}";
    }

    // Строка письма о своём списке: название, тип, дата и состояние позиций
    private function describeOwn(Wishlist $wishlist, CarbonImmutable $today): array
    {
        $total = $wishlist->items_count;
        $selected = $wishlist->selected_items_count;

        $details = match (true) {
            $wishlist->isTodo() => 'Не выполнено дел: '.($total - $selected)." из {$total}",
            // В режиме сюрприза владелец не знает, что выбрали гости
            $wishlist->isGift() && ! $wishlist->hide_selections && $total > 0 => "Выбрано позиций: {$selected} из {$total}",
            default => null,
        };

        return [
            'title' => $wishlist->title,
            'type' => $this->typeLabel($wishlist),
            'date' => $this->dateLabel($wishlist, $today),
            'details' => $details,
        ];
    }

    // Строка письма о чужом списке: владелец, название, тип, дата и состояние позиций
    private function describeSaved(Wishlist $wishlist, CarbonImmutable $today): array
    {
        $total = $wishlist->items_count;
        $selected = $wishlist->selected_items_count;

        $details = match (true) {
            $wishlist->isTodo() => 'Не выполнено дел: '.($total - $selected)." из {$total}",
            $wishlist->isGift() && $total > 0 => 'Свободно позиций: '.($total - $selected)." из {$total}",
            default => null,
        };

        return [
            'title' => $wishlist->title,
            'type' => $this->typeLabel($wishlist),
            'owner' => $wishlist->user->username,
            'date' => $this->dateLabel($wishlist, $today),
            'details' => $details,
            'url' => url($this->sharedPath($wishlist)),
        ];
    }

    private function typeLabel(Wishlist $wishlist): string
    {
        return match ($wishlist->type) {
            WishlistType::Todo => 'Список дел',
            WishlistType::Fund => 'Сбор',
            default => 'Список желаний',
        };
    }

    // Путь общей страницы, как во фронтенде (lib/sharedLink.ts)
    private function sharedPath(Wishlist $wishlist): string
    {
        return match ($wishlist->type) {
            WishlistType::Todo => "/shared-todolist/{$wishlist->share_token}",
            WishlistType::Fund => "/shared-fund/{$wishlist->share_token}",
            default => "/shared-wishlist/{$wishlist->share_token}",
        };
    }
}
