export interface WishlistItem {
    id?: string;
    // Нет в ответе владельцу, если у списка включён режим сюрприза
    isSelected?: boolean;
    label: string;
    // Ссылка на товар (http или https); null, если не указана
    url?: string | null;
    // Приоритет позиции; null, если не указан
    priority?: WishlistItemPriority | null;
}

// Приоритет позиции, совпадает с App\Enums\WishlistItemPriority на бэкенде:
// 1 — было бы неплохо, 2 — хочу, 3 — очень хочу
export type WishlistItemPriority = 1 | 2 | 3;

// Ключ цвета фона списка, совпадает с App\Enums\WishlistColor на бэкенде
export type WishlistColor =
    | 'white'
    | 'lavender'
    | 'pink'
    | 'peach'
    | 'lemon'
    | 'mint'
    | 'sky';

export interface Wishlist {
    id: string;
    title: string;
    color: WishlistColor;
    // Дата создания (ISO 8601); приходит только в дашборде, в общем списке её нет
    createdAt?: string;
    // Режим сюрприза: выбор гостей скрыт от владельца; приходит только в дашборде
    hideSelections?: boolean;
    username: string | null;
    items: WishlistItem[];
    // Бронь гостя: приходит на общей странице после сохранения или отмены выбора
    reservation?: WishlistReservation;
}

// Бронь гостя: позиции, выбранные за одно сохранение. По токену выбор можно отменить
export interface WishlistReservation {
    token: string;
    itemIds: string[];
}

// Данные формы создания и редактирования списка
export type WishlistForm = Pick<Wishlist, 'title' | 'color' | 'items'> & {
    hideSelections: boolean;
};
