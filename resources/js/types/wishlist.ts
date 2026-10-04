export interface WishlistItem {
    id?: string;
    // Нет в ответе владельцу, если у списка включён режим сюрприза
    isSelected?: boolean;
    label: string;
    // Ссылка на товар (http или https); null, если не указана
    url?: string | null;
}

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
}

// Данные формы создания и редактирования списка
export type WishlistForm = Pick<Wishlist, 'title' | 'color' | 'items'> & {
    hideSelections: boolean;
};
