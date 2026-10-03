import type { WishlistColor } from '@/types/wishlist';

// Цвета фона списка в порядке вывода в палитре.
// Оттенки задаются в resources/scss/ui/wishlistColors.scss
export const WISHLIST_COLORS: { value: WishlistColor; label: string }[] = [
    { value: 'white', label: 'Белый' },
    { value: 'lavender', label: 'Лавандовый' },
    { value: 'pink', label: 'Розовый' },
    { value: 'peach', label: 'Персиковый' },
    { value: 'lemon', label: 'Лимонный' },
    { value: 'mint', label: 'Мятный' },
    { value: 'sky', label: 'Голубой' },
];
