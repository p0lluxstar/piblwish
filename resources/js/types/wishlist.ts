export interface WishlistItem {
    id?: string;
    isSelected: boolean;
    label: string;
}

export interface Wishlist {
    id: string;
    title: string;
    items: WishlistItem[];
}
