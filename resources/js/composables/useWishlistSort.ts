import { computed, type ComputedRef, type Ref, ref, watch } from 'vue';

// date — дата создания (в архиве — переноса в архив), due — дата списка
// (срок списка дел и сбора, дата события списка желаний), title — название
export type WishlistSortField = 'date' | 'due' | 'title';
export type SortDirection = 'asc' | 'desc';

export interface WishlistSortState {
    field: WishlistSortField;
    direction: SortDirection;
}

// Естественное направление поля: даты создания — новые сверху,
// сроки — ближайшие сверху, названия — от А до Я
const NATURAL_DIRECTION: Record<WishlistSortField, SortDirection> = {
    date: 'desc',
    due: 'asc',
    title: 'asc',
};

const isSortState = (
    value: unknown,
    fields: WishlistSortField[],
): value is WishlistSortState => {
    if (typeof value !== 'object' || value === null) return false;

    const { field, direction } = value as Record<string, unknown>;

    return (
        fields.includes(field as WishlistSortField) &&
        (direction === 'asc' || direction === 'desc')
    );
};

// Чтение защищено: в приватном режиме доступ к localStorage может выбросить исключение,
// а сохранённое значение может оказаться повреждённым, устаревшим или относиться
// к полю, которого в этом разделе нет
const readSort = (
    storageKey: string,
    fields: WishlistSortField[],
    defaultSort: WishlistSortState,
): WishlistSortState => {
    try {
        const parsed: unknown = JSON.parse(
            window.localStorage.getItem(storageKey) ?? 'null',
        );

        if (isSortState(parsed, fields)) return parsed;
    } catch {
        // Значение отсутствует или некорректно — используется сортировка по умолчанию
    }

    return defaultSort;
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

export interface WishlistSortConfig<T> {
    storageKey: string;
    // Поля, по которым можно сортировать в разделе; первое — по умолчанию
    fields: WishlistSortField[];
    // Значения полей карточки; пустая строка — значения нет
    dateOf?: (item: T) => string;
    dueDateOf?: (item: T) => string;
    titleOf: (item: T) => string;
}

// Сортировка карточек списков (свои в дашборде и архиве, чужие в разделе
// «Чужие списки»); выбор пользователя хранится в localStorage
export const useWishlistSort = <T>(
    items: Ref<T[]>,
    {
        storageKey,
        fields,
        dateOf = (): string => '',
        dueDateOf = (): string => '',
        titleOf,
    }: WishlistSortConfig<T>,
): {
    sort: Ref<WishlistSortState>;
    setSort: (field: WishlistSortField) => void;
    isDefaultSort: ComputedRef<boolean>;
    resetSort: () => void;
    sortedItems: ComputedRef<T[]>;
} => {
    const defaultSort: WishlistSortState = {
        field: fields[0],
        direction: NATURAL_DIRECTION[fields[0]],
    };
    const sort = ref<WishlistSortState>(
        readSort(storageKey, fields, defaultSort),
    );

    watch(sort, (value) => writeSort(storageKey, value));

    // Повторное нажатие на активную кнопку меняет направление,
    // новое поле выбирается с естественным направлением
    const setSort = (field: WishlistSortField): void => {
        if (sort.value.field === field) {
            sort.value = {
                field,
                direction: sort.value.direction === 'asc' ? 'desc' : 'asc',
            };

            return;
        }

        sort.value = { field, direction: NATURAL_DIRECTION[field] };
    };

    const isDefaultSort = computed(
        () =>
            sort.value.field === defaultSort.field &&
            sort.value.direction === defaultSort.direction,
    );

    const resetSort = (): void => {
        sort.value = { ...defaultSort };
    };

    const valueOf: Record<WishlistSortField, (item: T) => string> = {
        date: dateOf,
        due: dueDateOf,
        title: titleOf,
    };

    const sortedItems = computed(() => {
        const { field, direction } = sort.value;
        const factor = direction === 'asc' ? 1 : -1;
        const getValue = valueOf[field];

        return [...items.value].sort((a, b) => {
            const valueA = getValue(a);
            const valueB = getValue(b);

            // Карточки без значения (список без даты, недоступный чужой список)
            // в любом направлении стоят в конце
            if (!valueA || !valueB) {
                return Number(!valueA) - Number(!valueB);
            }

            return field === 'title'
                ? factor * collator.compare(valueA, valueB)
                : // Даты в формате ISO 8601 упорядочиваются при сравнении как строки
                  factor * valueA.localeCompare(valueB);
        });
    });

    return { sort, setSort, isDefaultSort, resetSort, sortedItems };
};
