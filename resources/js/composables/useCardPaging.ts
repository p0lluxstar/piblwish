import {
    computed,
    type ComputedRef,
    type Ref,
    ref,
    watch,
    type WatchSource,
} from 'vue';

// Размер порции карточек на страницах разделов
export const CARD_PAGE_SIZE = 16;

interface CardPaging<T> {
    visibleItems: ComputedRef<T[]>;
    hiddenCount: ComputedRef<number>;
    nextCount: ComputedRef<number>;
    showMore: () => void;
    showAll: () => void;
}

// Карточки выводятся порциями: все списки уже загружены, ограничивается только
// отрисовка. Следующая порция добавляется кнопкой «Показать ещё», остальные
// карточки сразу — кнопкой «Показать все» (components/ui/ShowMoreButtons.vue).
// resetOn — сортировка и фильтр: после их смены набор карточек другой, поэтому
// вывод начинается с первой порции
export const useCardPaging = <T>(
    items: Ref<T[]>,
    resetOn: WatchSource[],
): CardPaging<T> => {
    const visibleCount = ref(CARD_PAGE_SIZE);

    const visibleItems = computed(() =>
        items.value.slice(0, visibleCount.value),
    );
    const hiddenCount = computed(() =>
        Math.max(items.value.length - visibleCount.value, 0),
    );
    // Размер следующей порции: последняя может быть меньше CARD_PAGE_SIZE
    const nextCount = computed(() =>
        Math.min(hiddenCount.value, CARD_PAGE_SIZE),
    );

    const showMore = (): void => {
        visibleCount.value += CARD_PAGE_SIZE;
    };

    const showAll = (): void => {
        visibleCount.value = items.value.length;
    };

    watch(resetOn, () => {
        visibleCount.value = CARD_PAGE_SIZE;
    });

    return { visibleItems, hiddenCount, nextCount, showMore, showAll };
};
