import type { WishlistType } from '../types/wishlist';

// Дата списка приходит с сервера без времени (Y-m-d). Дни считаются по местной
// дате пользователя: разбор через new Date('2026-12-31') дал бы полночь UTC,
// и западнее Гринвича дата сместилась бы на день назад
const parseDate = (value: string): Date => {
    const [year, month, day] = value.split('-').map(Number);

    return new Date(year, month - 1, day);
};

// Сегодняшняя местная дата в формате Y-m-d: значение для <input type="date">
export const todayDateString = (): string => {
    const now = new Date();
    const pad = (part: number): string => String(part).padStart(2, '0');

    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
};

// Сколько дней осталось до даты: 0 — сегодня, отрицательное число — дата прошла.
// Math.round сглаживает час разницы при переходе на летнее время
export const daysUntil = (value: string): number => {
    const now = new Date();
    const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());

    return Math.round(
        (parseDate(value).getTime() - today.getTime()) / 86400000,
    );
};

// «31 декабря 2026 г.»
export const formatDueDate = (value: string): string =>
    parseDate(value).toLocaleDateString('ru-RU', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });

// «1 день», «2 дня», «5 дней», «21 день»
export const formatDays = (count: number): string => {
    const lastTwo = count % 100;
    const last = count % 10;

    if (lastTwo >= 11 && lastTwo <= 14) return `${count} дней`;
    if (last === 1) return `${count} день`;
    if (last >= 2 && last <= 4) return `${count} дня`;

    return `${count} дней`;
};

// Подпись к дате: «Сделать до» у списка дел, «Дата события» у списка желаний
export const dueDateTitle = (type: WishlistType): string =>
    type === 'todo' ? 'Сделать до' : 'Дата события';

// soon — срок списка дел через 3 дня или раньше, today — дата наступила сегодня,
// overdue — срок списка дел прошёл
export type DueDateTone = 'normal' | 'soon' | 'today' | 'overdue';

export interface DueDateStatus {
    // «Осталось 5 дней», «Сегодня», «Просрочено на 2 дня»
    text: string;
    tone: DueDateTone;
}

// Сколько осталось до даты списка; null — счётчик не показывается:
// все дела выполнены или событие списка желаний уже прошло
export const getDueDateStatus = (
    type: WishlistType,
    dueDate: string,
    isCompleted: boolean,
): DueDateStatus | null => {
    const days = daysUntil(dueDate);

    if (type === 'todo') {
        if (isCompleted) return null;

        if (days < 0) {
            return {
                text: `Просрочено на ${formatDays(-days)}`,
                tone: 'overdue',
            };
        }

        if (days === 0) return { text: 'Срок сегодня', tone: 'today' };
        if (days === 1) return { text: 'Срок завтра', tone: 'soon' };

        // «Остался 21 день», «Осталось 5 дней»
        const verb =
            days % 10 === 1 && days % 100 !== 11 ? 'Остался' : 'Осталось';

        return {
            text: `${verb} ${formatDays(days)}`,
            tone: days <= 3 ? 'soon' : 'normal',
        };
    }

    if (days < 0) return null;
    if (days === 0) return { text: 'Сегодня', tone: 'today' };
    if (days === 1) return { text: 'Завтра', tone: 'normal' };

    return { text: `Через ${formatDays(days)}`, tone: 'normal' };
};
