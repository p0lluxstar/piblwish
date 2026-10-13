<?php

namespace App\Services\SavedWishlist;

use App\Models\SavedWishlist;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Validation\ValidationException;

/**
 * Чужие списки, добавленные пользователем к себе с общей страницы.
 *
 * Добавить можно только список, который открывается по ссылке (Wishlist::openByLink),
 * и только чужой. Если владелец потом закрыл доступ к списку или сменил ссылку,
 * закладка остаётся, но SavedWishlistResource отдаёт её как недоступную,
 * без содержимого списка. Закладки адресуются токеном ссылки, а не id списка:
 * id гостю неизвестен.
 */
class SavedWishlistService
{
    /**
     * Закладки пользователя, последние добавленные — первыми.
     *
     * @return Collection<int, SavedWishlist>
     */
    public function getSavedWishlists(User $user): Collection
    {
        return $user->savedWishlists()
            ->with(['wishlist' => fn ($query) => $this->withSummary($query)])
            ->latest()
            ->latest('id')
            ->get();
    }

    // Добавить список к себе по токену ссылки; повторное добавление возвращает прежнюю
    // закладку, а добавление по новой ссылке после её смены снова делает закладку доступной
    public function saveWishlist(User $user, string $shareToken): SavedWishlist
    {
        $wishlist = Wishlist::query()->openByLink()->byShareToken($shareToken)->firstOrFail();

        if ($wishlist->user_id === $user->id) {
            throw ValidationException::withMessages([
                'wishlist' => 'Свой список нельзя добавить в чужие',
            ]);
        }

        $saved = $user->savedWishlists()->updateOrCreate(
            ['wishlist_id' => $wishlist->id],
            ['share_token' => $wishlist->share_token],
        );

        return $saved->load(['wishlist' => fn ($query) => $this->withSummary($query)]);
    }

    // Убрать список из своих чужих списков по токену ссылки, с которой его добавили;
    // отсутствующая закладка не считается ошибкой
    public function removeSavedWishlist(User $user, string $shareToken): void
    {
        $user->savedWishlists()
            ->where('share_token', $shareToken)
            ->delete();
    }

    // Владелец и число позиций для карточки; сами позиции не загружаются
    private function withSummary(Builder|Relation $query): void
    {
        $query->with('user')->withCount([
            'items',
            'items as selected_items_count' => fn (Builder $query) => $query->where('is_selected', true),
        ]);
    }
}
