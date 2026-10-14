<?php

namespace App\Services\SharedWishlist;

use App\Models\Wishlist;
use Illuminate\Database\Eloquent\Builder;

/**
 * Мета-теги общей страницы списка для превью ссылки в мессенджерах и соцсетях.
 *
 * Боты мессенджеров не выполняют JavaScript, поэтому название и описание
 * подставляются в HTML страницы на сервере. Список, который не открывается
 * по ссылке, ничего о себе не сообщает: страница получает общие теги сайта.
 */
class SharedPageMetaService
{
    /**
     * @return array{title: string, description: string}|null
     */
    public function getMeta(string $token): ?array
    {
        $wishlist = Wishlist::query()
            ->openByLink()
            ->byShareToken($token)
            ->with('user:id,username')
            ->withCount([
                'items',
                'items as selected_items_count' => fn (Builder $query) => $query->where('is_selected', true),
            ])
            ->first();

        if ($wishlist === null) {
            return null;
        }

        return [
            'title' => $wishlist->title,
            'description' => $this->description($wishlist),
        ];
    }

    // «Список желаний ivan: 5 желаний. Дата события: 14 октября 2026.»
    private function description(Wishlist $wishlist): string
    {
        $owner = $wishlist->user->username;
        $total = $wishlist->items_count;

        [$kind, $details, $dateLabel, $call] = match (true) {
            $wishlist->isTodo() => [
                'Список дел',
                $total > 0 ? "выполнено {$wishlist->selected_items_count} из {$total}" : null,
                'Сделать до',
                null,
            ],
            $wishlist->isFund() => [
                'Сбор',
                $total > 0 ? self::plural($total, 'цель', 'цели', 'целей') : null,
                'Сбор до',
                null,
            ],
            default => [
                'Список желаний',
                $total > 0 ? self::plural($total, 'желание', 'желания', 'желаний') : null,
                'Дата события',
                'Откройте ссылку и выберите подарок.',
            ],
        };

        $parts = [$kind.' '.$owner.($details !== null ? ": {$details}" : '').'.'];

        if ($wishlist->due_date !== null) {
            $parts[] = $dateLabel.': '.$wishlist->due_date->locale('ru')->translatedFormat('j F Y').'.';
        }

        if ($call !== null) {
            $parts[] = $call;
        }

        return implode(' ', $parts);
    }

    // «1 желание», «3 желания», «5 желаний»
    private static function plural(int $count, string $one, string $few, string $many): string
    {
        $mod10 = $count % 10;
        $mod100 = $count % 100;

        $word = match (true) {
            $mod10 === 1 && $mod100 !== 11 => $one,
            $mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14) => $few,
            default => $many,
        };

        return "{$count} {$word}";
    }
}
