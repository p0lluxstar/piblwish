import type { WishlistType } from '@/types/wishlist';

// Путь общей страницы списка: у списка дел и у списка желаний свои адреса.
// Оба адреса открывают одну страницу, а она после загрузки исправляет адрес по типу
export const getSharedWishlistPath = (
    id: string,
    type: WishlistType,
): string =>
    type === 'todo' ? `/shared-todolist/${id}` : `/shared-wishlist/${id}`;

// Полная ссылка на общую страницу для копирования
export const getSharedWishlistUrl = (
    id: string,
    type: WishlistType,
): string => {
    const appUrl = import.meta.env.VITE_API_URL || window.location.origin;

    return `${appUrl}${getSharedWishlistPath(id, type)}`;
};
