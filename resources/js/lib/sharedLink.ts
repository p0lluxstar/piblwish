import type { WishlistType } from '@/types/wishlist';

// Путь общей страницы списка: у списка дел, сбора и списка желаний свои адреса.
// Все они открывают одну страницу, а она после загрузки исправляет адрес по типу
export const getSharedWishlistPath = (
    id: string,
    type: WishlistType,
): string => {
    if (type === 'todo') return `/shared-todolist/${id}`;
    if (type === 'fund') return `/shared-fund/${id}`;

    return `/shared-wishlist/${id}`;
};

// Полная ссылка на общую страницу для копирования
export const getSharedWishlistUrl = (
    id: string,
    type: WishlistType,
): string => {
    const appUrl = import.meta.env.VITE_API_URL || window.location.origin;

    return `${appUrl}${getSharedWishlistPath(id, type)}`;
};
