import type { WishlistItemCheckedBy } from '@/types/wishlist';

// Имя того, кто отметил дело выполненным, для подписи рядом с делом.
// ownerName — имя владельца для отметок, которые поставил он сам; null — отметки
// владельца не подписываются (на его собственной карточке)
export const checkedByName = (
    checkedBy: WishlistItemCheckedBy | null | undefined,
    ownerName: string | null,
): string | null => {
    if (!checkedBy) return null;

    if (checkedBy.guest) {
        return checkedBy.name ?? 'Гость';
    }

    return ownerName;
};
