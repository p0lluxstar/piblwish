import type { WishlistItemPriority } from '../types/wishlist';

// Значения по возрастанию: число сердец (у дел — молний) совпадает со значением
export const ITEM_PRIORITIES: readonly WishlistItemPriority[] = [1, 2, 3];

export const ITEM_PRIORITY_LABELS: Record<WishlistItemPriority, string> = {
    1: 'Было бы неплохо',
    2: 'Хочу',
    3: 'Очень хочу',
};

// У дела то же поле priority означает важность
export const ITEM_IMPORTANCE_LABELS: Record<WishlistItemPriority, string> = {
    1: 'Можно отложить',
    2: 'Важно',
    3: 'Очень важно',
};

export const itemPriorityLabels = (
    todo: boolean,
): Record<WishlistItemPriority, string> =>
    todo ? ITEM_IMPORTANCE_LABELS : ITEM_PRIORITY_LABELS;
