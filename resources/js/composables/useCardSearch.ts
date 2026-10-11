import { computed, type ComputedRef, type Ref, ref } from 'vue';

// Строка для сравнения: без регистра, «ё» совпадает с «е», лишние пробелы убраны
const normalize = (value: string): string =>
    value.toLowerCase().replace(/ё/g, 'е').replace(/\s+/g, ' ').trim();

// Поиск карточек по тексту на клиенте: все списки раздела уже загружены.
// textsOf возвращает строки карточки, в которых ищется запрос (название,
// текст заметки, имя владельца). Запрос не сохраняется между посещениями:
// забытый запрос скрыл бы часть карточек. Поиск показывается, когда карточек
// больше одной, как и панель с ним; иначе запрос не действует
export const useCardSearch = <T>(
    items: Ref<T[]>,
    textsOf: (item: T) => (string | null | undefined)[],
): {
    searchQuery: Ref<string>;
    hasSearch: ComputedRef<boolean>;
    activeSearchQuery: ComputedRef<string>;
    filterBySearch: (list: T[]) => T[];
} => {
    const searchQuery = ref('');

    const hasSearch = computed(() => items.value.length > 1);

    const activeSearchQuery = computed(() =>
        hasSearch.value ? normalize(searchQuery.value) : '',
    );

    const filterBySearch = (list: T[]): T[] => {
        const query = activeSearchQuery.value;

        if (!query) return list;

        return list.filter((item) =>
            textsOf(item).some(
                (text) => !!text && normalize(text).includes(query),
            ),
        );
    };

    return { searchQuery, hasSearch, activeSearchQuery, filterBySearch };
};
