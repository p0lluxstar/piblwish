export interface WishlistItem {
    label: string;
    isSelected?: boolean;
}

export interface Wishlist {
    id: string;
    title: string;
    items: WishlistItem[];
}
