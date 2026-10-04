import type { WishlistItemPriority } from '../types/wishlist';

// Значения по возрастанию: число сердец совпадает со значением приоритета
export const ITEM_PRIORITIES: readonly WishlistItemPriority[] = [1, 2, 3];

export const ITEM_PRIORITY_LABELS: Record<WishlistItemPriority, string> = {
    1: 'Было бы неплохо',
    2: 'Хочу',
    3: 'Очень хочу',
};
