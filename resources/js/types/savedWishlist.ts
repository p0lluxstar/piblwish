import type { WishlistColor } from './wishlist';

// Чужой список в разделе «Чужие списки»: сводка для карточки, позиции открываются
// на общей странице. Если владелец закрыл доступ к списку, available = false
// и поля самого списка равны null: известны только владелец и дата добавления
export interface SavedWishlist {
    // id самого списка: по нему открывается общая страница и убирается закладка
    id: string;
    available: boolean;
    savedAt: string;
    username: string | null;
    avatarUrl: string | null;
    // У чужих списков бывают список желаний, список дел и сбор
    type: 'gift' | 'todo' | 'fund' | null;
    title: string | null;
    color: WishlistColor | null;
    // Срок списка дел или сбора, дата события списка желаний (Y-m-d)
    dueDate: string | null;
    itemsCount: number | null;
    // Выбранные гостями подарки или выполненные дела
    selectedCount: number | null;
}
