import type { WishlistType } from '@/types/wishlist';

// Путь общей страницы списка: у списка дел свой адрес, у списка желаний общий.
// Оба адреса открывают одну страницу, а она после загрузки исправляет адрес по типу
export const getSharedWishlistPath = (
    id: string,
    type: WishlistType,
): string => (type === 'todo' ? `/shared-todolists/${id}` : `/shared/${id}`);

// Полная ссылка на общую страницу для копирования
export const getSharedWishlistUrl = (
    id: string,
    type: WishlistType,
): string => {
    const appUrl = import.meta.env.VITE_API_URL || window.location.origin;

    return `${appUrl}${getSharedWishlistPath(id, type)}`;
};
