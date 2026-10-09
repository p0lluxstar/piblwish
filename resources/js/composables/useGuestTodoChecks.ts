import { ref } from 'vue';

import type { WishlistItem } from '@/types/wishlist';

// Дела, которые гость отметил из этого браузера: id дела → имя, под которым
// он его отметил (null — без имени)
type GuestChecks = Record<string, string | null>;

const storageKey = (wishlistId: string): string => `todo-checked:${wishlistId}`;

// Чтение и запись защищены: в приватном режиме доступ к localStorage может выбросить
// исключение, а сохранённое значение может оказаться повреждённым
const readChecks = (wishlistId: string): GuestChecks => {
    try {
        const parsed: unknown = JSON.parse(
            window.localStorage.getItem(storageKey(wishlistId)) ?? '{}',
        );

        if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) {
            return {};
        }

        return Object.fromEntries(
            Object.entries(parsed).filter(
                (entry): entry is [string, string | null] =>
                    entry[1] === null || typeof entry[1] === 'string',
            ),
        );
    } catch {
        return {};
    }
};

const writeChecks = (wishlistId: string, checks: GuestChecks): void => {
    try {
        if (Object.keys(checks).length === 0) {
            window.localStorage.removeItem(storageKey(wishlistId));
        } else {
            window.localStorage.setItem(
                storageKey(wishlistId),
                JSON.stringify(checks),
            );
        }
    } catch {
        // Сохранение недоступно: после перезагрузки свои отметки подпишутся именем
    }
};

// Отметка дела совпадает с сохранённой: её ставил гость под тем же именем.
// Если владелец снял отметку, а дело снова отметил кто-то другой, имя
// обычно не совпадёт, и подписи «Вы» не будет
const matches = (item: WishlistItem, name: string | null): boolean =>
    Boolean(item.checkedBy?.guest) && (item.checkedBy?.name ?? null) === name;

// Свои отметки гостя на общей странице списка дел. Хранятся в localStorage
// этого браузера, поэтому на другом устройстве подписываются обычным именем
export const useGuestTodoChecks = (
    wishlistId: string,
): {
    isOwnCheck: (item: WishlistItem) => boolean;
    remember: (
        items: WishlistItem[],
        checkedIds: string[],
        name: string | null,
    ) => void;
    prune: (items: WishlistItem[]) => void;
} => {
    const checks = ref<GuestChecks>(readChecks(wishlistId));

    const isOwnCheck = (item: WishlistItem): boolean =>
        item.id !== undefined &&
        item.id in checks.value &&
        matches(item, checks.value[item.id]);

    // После отметки: запоминаются дела из запроса, отмеченные этим гостем.
    // Дело, которое одновременно успел отметить другой гость, сервер не меняет,
    // и оно не запоминается
    const remember = (
        items: WishlistItem[],
        checkedIds: string[],
        name: string | null,
    ): void => {
        const ids = new Set(checkedIds);
        const next = { ...checks.value };

        for (const item of items) {
            if (item.id && ids.has(item.id) && matches(item, name)) {
                next[item.id] = name;
            }
        }

        checks.value = next;
        writeChecks(wishlistId, next);
    };

    // Забываются дела, с которых отметку сняли или которые удалены
    const prune = (items: WishlistItem[]): void => {
        const next: GuestChecks = {};

        for (const item of items) {
            if (item.id !== undefined && isOwnCheck(item)) {
                next[item.id] = checks.value[item.id];
            }
        }

        if (Object.keys(next).length !== Object.keys(checks.value).length) {
            checks.value = next;
            writeChecks(wishlistId, next);
        }
    };

    return { isOwnCheck, remember, prune };
};
