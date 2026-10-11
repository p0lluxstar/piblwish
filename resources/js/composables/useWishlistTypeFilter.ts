import {
    CreditCardCheck,
    Gift,
    LayoutList,
    ListChecks,
    StickyNote,
} from '@lucide/vue';
import {
    type Component,
    computed,
    type ComputedRef,
    type Ref,
    ref,
    watch,
} from 'vue';

import type { WishlistType } from '@/types/wishlist';

export type WishlistTypeFilter = 'all' | WishlistType;

export interface WishlistTypeFilterOption {
    value: WishlistTypeFilter;
    label: string;
    icon: Component;
    count: number;
}

// Иконка заменяет подпись кнопки на очень узком экране
const TYPE_FILTER_OPTIONS: Omit<WishlistTypeFilterOption, 'count'>[] = [
    { value: 'all', label: 'Все', icon: LayoutList },
    { value: 'gift', label: 'Желания', icon: Gift },
    { value: 'todo', label: 'Дела', icon: ListChecks },
    { value: 'fund', label: 'Сборы', icon: CreditCardCheck },
    { value: 'note', label: 'Заметки', icon: StickyNote },
];

const isWishlistType = (value: unknown): value is WishlistType =>
    value === 'gift' ||
    value === 'todo' ||
    value === 'fund' ||
    value === 'note';

// Чтение защищено так же, как у сортировки: localStorage может быть недоступен
const readTypeFilter = (storageKey: string): WishlistTypeFilter => {
    try {
        const value = window.localStorage.getItem(storageKey);

        if (isWishlistType(value)) return value;
    } catch {
        // Значение недоступно — показываются все списки
    }

    return 'all';
};

// Фильтр карточек по типу списка; выбор хранится в localStorage.
// typeOf возвращает null у карточки без известного типа (недоступный чужой
// список): она учитывается только в «Все»
export const useWishlistTypeFilter = <T>(
    items: Ref<T[]>,
    {
        storageKey,
        typeOf,
    }: {
        storageKey: string;
        typeOf: (item: T) => WishlistType | null;
    },
): {
    typeFilter: Ref<WishlistTypeFilter>;
    typeFilterOptions: ComputedRef<WishlistTypeFilterOption[]>;
    hasTypeFilter: ComputedRef<boolean>;
    activeTypeFilter: ComputedRef<WishlistTypeFilter>;
    filterByType: (list: T[]) => T[];
} => {
    const typeFilter = ref<WishlistTypeFilter>(readTypeFilter(storageKey));

    watch(typeFilter, (value) => {
        try {
            window.localStorage.setItem(storageKey, value);
        } catch {
            // Сохранение недоступно — фильтр действует до перезагрузки страницы
        }
    });

    const typeCounts = computed(() => {
        const counts: Record<WishlistTypeFilter, number> = {
            all: items.value.length,
            gift: 0,
            todo: 0,
            fund: 0,
            note: 0,
        };

        for (const item of items.value) {
            const type = typeOf(item);

            if (type) counts[type] += 1;
        }

        return counts;
    });

    // Кнопки показываются только для типов, которые есть среди карточек
    const typeFilterOptions = computed(() =>
        TYPE_FILTER_OPTIONS.map((option) => ({
            ...option,
            count: typeCounts.value[option.value],
        })).filter((option) => option.value === 'all' || option.count > 0),
    );

    // Фильтр нужен, только когда есть карточки хотя бы двух типов
    const hasTypeFilter = computed(() => typeFilterOptions.value.length > 2);

    // Фильтр действует, только если он показан и у выбранного типа есть карточки.
    // Иначе сохранённый фильтр спрятал бы все карточки без возможности его сбросить
    const activeTypeFilter = computed<WishlistTypeFilter>(() =>
        hasTypeFilter.value &&
        typeFilter.value !== 'all' &&
        typeCounts.value[typeFilter.value] > 0
            ? typeFilter.value
            : 'all',
    );

    const filterByType = (list: T[]): T[] =>
        activeTypeFilter.value === 'all'
            ? list
            : list.filter((item) => typeOf(item) === activeTypeFilter.value);

    return {
        typeFilter,
        typeFilterOptions,
        hasTypeFilter,
        activeTypeFilter,
        filterByType,
    };
};
