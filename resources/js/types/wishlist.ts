export interface WishlistItem {
    id?: string;
    // Нет в ответе владельцу, если у списка включён режим сюрприза
    isSelected?: boolean;
    label: string;
    // Ссылка на товар (http или https); null, если не указана
    url?: string | null;
    // Приоритет позиции; null, если не указан
    priority?: WishlistItemPriority | null;
    // Стоимость в целых рублях (0–10 000 000); null, если не указана
    price?: number | null;
}

// Приоритет позиции, совпадает с App\Enums\WishlistItemPriority на бэкенде:
// 1 — было бы неплохо, 2 — хочу, 3 — очень хочу
export type WishlistItemPriority = 1 | 2 | 3;

// Тип списка, совпадает с App\Enums\WishlistType на бэкенде:
// gift — список желаний, todo — список дел (isSelected позиции означает «выполнено»),
// note — заметка: вместо названия и позиций только текст content
export type WishlistType = 'gift' | 'todo' | 'note';

// Ключ цвета фона списка, совпадает с App\Enums\WishlistColor на бэкенде
export type WishlistColor =
    | 'white'
    | 'lavender'
    | 'pink'
    | 'peach'
    | 'lemon'
    | 'mint'
    | 'sky';

export interface Wishlist {
    id: string;
    // Тип списка; приходит только в дашборде: общая страница доступна лишь для списков желаний
    type?: WishlistType;
    // У заметки названия нет: null
    title: string | null;
    // Текст заметки; у списков желаний и дел — null
    content?: string | null;
    color: WishlistColor;
    // Дата создания (ISO 8601); приходит только в дашборде, в общем списке её нет
    createdAt?: string;
    // Режим сюрприза: выбор гостей скрыт от владельца; приходит только в дашборде
    hideSelections?: boolean;
    username: string | null;
    items: WishlistItem[];
    // Бронь гостя: приходит на общей странице после сохранения или отмены выбора
    reservation?: WishlistReservation;
}

// Бронь гостя: позиции, выбранные за одно сохранение. По токену выбор можно отменить
export interface WishlistReservation {
    token: string;
    itemIds: string[];
}

// Данные формы создания и редактирования списка или заметки
export type WishlistForm = Pick<Wishlist, 'color' | 'items'> & {
    type: WishlistType;
    title: string;
    content: string;
    hideSelections: boolean;
};

// Данные запроса на создание: у списка нет content, у заметки — title, items
// и hideSelections, поэтому эти поля необязательны
export type WishlistCreatePayload = Pick<WishlistForm, 'type' | 'color'> &
    Partial<
        Pick<WishlistForm, 'title' | 'content' | 'hideSelections' | 'items'>
    >;

// Данные запроса на изменение: тип после создания не меняется
export type WishlistUpdatePayload = Omit<WishlistCreatePayload, 'type'> & {
    id: string;
};
