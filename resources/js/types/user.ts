import type { WishlistType } from '@/types/wishlist';

// Ключ фона приложения, совпадает с App\Enums\AppBackground на бэкенде
export type AppBackground =
    | 'blossom'
    | 'ocean'
    | 'mint'
    | 'sand'
    | 'mist'
    | 'stone'
    | 'cobalt';

export type User = {
    id: number;
    email: string;
    username?: string;
    background: AppBackground;
    // Показывать в дашборде строку «Скоро у друзей» с ближайшими датами чужих списков
    showFriendsEvents: boolean;
    // Присылать письма перед датой своих и сохранённых чужих списков
    emailReminders: boolean;
    // За сколько дней до даты списка приходит напоминание, 1–7
    reminderDays: number;
    // Адрес фотографии; null — фотографии нет, показывается первая буква имени
    avatarUrl: string | null;
    // Сколько карточек каждого типа создано за всё время, включая удалённые
    stats: Record<WishlistType, number>;
    // Дата регистрации в ISO 8601
    createdAt: string | null;
};
