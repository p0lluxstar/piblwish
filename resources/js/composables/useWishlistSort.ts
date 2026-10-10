import { computed, type ComputedRef, type Ref, ref, watch } from 'vue';

import type { Wishlist } from '@/types/wishlist';

export type WishlistSortField = 'date' | 'title';
export type SortDirection = 'asc' | 'desc';

export interface WishlistSortState {
    field: WishlistSortField;
    direction: SortDirection;
}

const STORAGE_KEY = 'wishlists-sort';
const DEFAULT_SORT: WishlistSortState = { field: 'date', direction: 'desc' };

const isSortState = (value: unknown): value is WishlistSortState => {
    if (typeof value !== 'object' || value === null) return false;

    const { field, direction } = value as Record<string, unknown>;

    return (
        (field === 'date' || field === 'title') &&
        (direction === 'asc' || direction === 'desc')
    );
};

// Чтение защищено: в приватном режиме доступ к localStorage может выбросить исключение,
// а сохранённое значение может оказаться повреждённым или устаревшим
const readSort = (storageKey: string): WishlistSortState => {
    try {
        const parsed: unknown = JSON.parse(
            window.localStorage.getItem(storageKey) ?? 'null',
        );

        if (isSortState(parsed)) return parsed;
    } catch {
        // Значение отсутствует или некорректно — используется сортировка по умолчанию
    }

    return DEFAULT_SORT;
};

const writeSort = (storageKey: string, value: WishlistSortState): void => {
    try {
        window.localStorage.setItem(storageKey, JSON.stringify(value));
    } catch {
        // Сохранение недоступно — сортировка действует до перезагрузки страницы
    }
};

const collator = new Intl.Collator('ru', {
    sensitivity: 'base',
    numeric: true,
});

// У заметки нет названия: при сортировке по названию учитывается её текст
const sortTitle = (wishlist: Wishlist): string =>
    wishlist.title ?? wishlist.content ?? '';

// Дата карточки для сортировки «По дате»: по умолчанию дата создания
const createdAtOf = (wishlist: Wishlist): string => wishlist.createdAt ?? '';

// Сортировка карточек списков в дашборде; выбор пользователя хранится в localStorage.
// Архив передаёт свой ключ хранения и сортирует по времени переноса в архив
export const useWishlistSort = (
    wishlists: Ref<Wishlist[]>,
    {
        storageKey = STORAGE_KEY,
        dateOf = createdAtOf,
    }: {
        storageKey?: string;
        dateOf?: (wishlist: Wishlist) => string;
    } = {},
): {
    sort: Ref<WishlistSortState>;
    setSort: (field: WishlistSortField) => void;
    sortedWishlists: ComputedRef<Wishlist[]>;
} => {
    const sort = ref<WishlistSortState>(readSort(storageKey));

    watch(sort, (value) => writeSort(storageKey, value));

    // Повторное нажатие на активную кнопку меняет направление.
    // Новое поле выбирается с естественным направлением: даты — новые сверху, названия — от А до Я
    const setSort = (field: WishlistSortField): void => {
        if (sort.value.field === field) {
            sort.value = {
                field,
                direction: sort.value.direction === 'asc' ? 'desc' : 'asc',
            };

            return;
        }

        sort.value = { field, direction: field === 'date' ? 'desc' : 'asc' };
    };

    const sortedWishlists = computed(() => {
        const { field, direction } = sort.value;
        const factor = direction === 'asc' ? 1 : -1;

        return [...wishlists.value].sort((a, b) =>
            field === 'title'
                ? factor * collator.compare(sortTitle(a), sortTitle(b))
                : // Даты в формате ISO 8601 упорядочиваются при сравнении как строки
                  factor * dateOf(a).localeCompare(dateOf(b)),
        );
    });

    return { sort, setSort, sortedWishlists };
};
