export interface WishlistItem {
    id?: string;
    isSelected: boolean;
    label: string;
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
    username: string | null;
    items: WishlistItem[];
}

// Данные формы создания и редактирования списка
export type WishlistForm = Pick<Wishlist, 'title' | 'color' | 'items'>;
