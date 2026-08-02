export interface WishlistItem {
    id?: string;
    isSelected: boolean;
    label: string;
}

export interface Wishlist {
    id: string;
    title: string;
    username: string | null;
    items: WishlistItem[];
}
