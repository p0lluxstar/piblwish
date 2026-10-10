export interface WishlistItem {
    id?: string;
    // Нет в ответе владельцу, если у списка включён режим сюрприза
    isSelected?: boolean;
    label: string;
    // Ссылки на товар (http или https), не больше трёх; пустой массив, если их нет.
    // В форме пустая строка — поле ссылки, которое ещё не заполнено
    urls?: string[];
    // Приоритет позиции; null, если не указан
    priority?: WishlistItemPriority | null;
    // Стоимость в целых рублях (0–10 000 000), у цели сбора — целевая сумма
    // (0–100 000 000); null, если не указана
    price?: number | null;
    // Совместный подарок; приходит только на общей странице, владельцу не отдаётся
    jointGift?: WishlistJointGift | null;
    // Кто отметил дело выполненным; есть только у позиций списка дел,
    // у невыполненного дела null
    checkedBy?: WishlistItemCheckedBy | null;
}

// Автор отметки выполненного дела: guest = false — владелец списка,
// guest = true — гость по ссылке; name — имя гостя или null, если он его не указал
export interface WishlistItemCheckedBy {
    guest: boolean;
    name: string | null;
}

// Совместный подарок: гость-организатор предлагает остальным гостям подарить позицию
// вместе. Деньги сервис не хранит, участники связываются с организатором сами
export interface WishlistJointGift {
    name: string;
    // Контакт выводится обычным текстом, без ссылки
    contact: string | null;
    comment: string | null;
}

// Данные формы совместного подарка: пустая строка в contact и comment означает «не указано»
export interface JointGiftDraft {
    name: string;
    contact: string;
    comment: string;
}

// Приоритет позиции, совпадает с App\Enums\WishlistItemPriority на бэкенде:
// 1 — было бы неплохо, 2 — хочу, 3 — очень хочу
export type WishlistItemPriority = 1 | 2 | 3;

// Тип списка, совпадает с App\Enums\WishlistType на бэкенде:
// gift — список желаний, todo — список дел (isSelected позиции означает «выполнено»),
// note — заметка: вместо названия и позиций только текст content,
// fund — сбор: позиции — цели с одной ссылкой на сбор (constants/fundHosts.ts)
// и целевой суммой в price
export type WishlistType = 'gift' | 'todo' | 'note' | 'fund';

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
    // Тип списка. На общей странице бывает gift, todo или fund: список дел и сбор
    // открываются по ссылке только для просмотра, если владелец включил доступ
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
    // Открывается ли список по общей ссылке: у списка желаний и списка дел — если
    // доступ включён, у заметки всегда false; приходит только в дашборде
    isShared?: boolean;
    // Могут ли гости по ссылке отмечать дела выполненными; у списка желаний и заметки false.
    // В дашборде — сохранённое разрешение, на общей странице — действующее
    guestsCanCheck?: boolean;
    // Должен ли гость указать имя, отмечая дела; у списка желаний и заметки false
    guestNameRequired?: boolean;
    // Срок списка дел или сбора, дата события списка желаний (Y-m-d); null — дата
    // не указана, у заметки всегда null
    dueDate?: string | null;
    // Время переноса в архив (ISO 8601); null — список не в архиве.
    // Приходит только в дашборде: архивный список по ссылке не открывается
    archivedAt?: string | null;
    username: string | null;
    // Фотография владельца; приходит только на общей странице
    avatarUrl?: string | null;
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
    // Доступ к списку по ссылке; у заметки не отправляется
    isShared: boolean;
    // Разрешение гостям отмечать дела по ссылке; у других типов не отправляется
    guestsCanCheck: boolean;
    // Обязательно ли гостю указывать имя; у других типов не отправляется
    guestNameRequired: boolean;
    // Дата списка (Y-m-d); пустая строка — дата не указана. У заметки не отправляется
    dueDate: string;
};

// Данные запроса на создание: у списка нет content, у заметки — title, items,
// hideSelections и isShared, а guestsCanCheck и guestNameRequired есть только у списка дел,
// поэтому эти поля необязательны
export type WishlistCreatePayload = Pick<WishlistForm, 'type' | 'color'> &
    Partial<
        Pick<
            WishlistForm,
            | 'title'
            | 'content'
            | 'hideSelections'
            | 'isShared'
            | 'guestsCanCheck'
            | 'guestNameRequired'
            | 'items'
        >
    > & {
        // null убирает дату при изменении
        dueDate?: string | null;
    };

// Данные запроса на изменение: тип после создания не меняется
export type WishlistUpdatePayload = Omit<WishlistCreatePayload, 'type'> & {
    id: string;
};
