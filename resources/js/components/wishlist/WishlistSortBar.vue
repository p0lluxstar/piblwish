<script setup lang="ts">
import { ArrowDown, ArrowUp, FunnelX } from '@lucide/vue';
import { computed } from 'vue';

import CardSearchInput from '@/components/ui/CardSearchInput.vue';
import type {
    WishlistSortField,
    WishlistSortState,
} from '@/composables/useWishlistSort';
import type {
    WishlistTypeFilter,
    WishlistTypeFilterOption,
} from '@/composables/useWishlistTypeFilter';

// Панель над карточками: поиск (лупа в начале, по клику раскрывается поле),
// фильтр по типу и сортировка. Используется в дашборде, архиве и разделе
// «Чужие списки»; в слот выводятся кнопки раздела справа. Кнопка «Сбросить»
// видна, когда введён запрос, выбран тип или сортировка не по умолчанию
const props = defineProps<{
    search: string;
    showSearch: boolean;
    searchPlaceholder: string;
    typeOptions: WishlistTypeFilterOption[];
    activeType: WishlistTypeFilter;
    showTypeFilter: boolean;
    sortOptions: { field: WishlistSortField; label: string }[];
    sort: WishlistSortState;
    showSort: boolean;
    isDefaultSort: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:search', value: string): void;
    (e: 'update:type', value: WishlistTypeFilter): void;
    (e: 'sort', field: WishlistSortField): void;
    (e: 'reset'): void;
}>();

const canReset = computed(
    () =>
        (props.showSearch && props.search.trim() !== '') ||
        props.activeType !== 'all' ||
        !props.isDefaultSort,
);

// Подпись для экранных дикторов: поле и текущее направление сортировки
const sortAriaLabel = (field: WishlistSortField, label: string): string => {
    if (props.sort.field !== field) return `Сортировать ${label.toLowerCase()}`;

    return `${label}, ${props.sort.direction === 'asc' ? 'по возрастанию' : 'по убыванию'}`;
};
</script>

<template>
    <div class="sort-bar">
        <CardSearchInput
            v-if="showSearch"
            class="card-search"
            :model-value="search"
            :placeholder="searchPlaceholder"
            @update:model-value="emit('update:search', $event)"
        />

        <div
            v-if="showTypeFilter"
            class="sort-group"
            role="group"
            aria-label="Тип списков"
        >
            <button
                v-for="option in typeOptions"
                :key="option.value"
                class="sort-btn"
                :class="{ active: activeType === option.value }"
                :aria-pressed="activeType === option.value"
                :aria-label="`${option.label}: ${option.count}`"
                :title="option.label"
                @click="emit('update:type', option.value)"
            >
                <component
                    :is="option.icon"
                    class="type-filter-icon"
                    :size="14"
                    aria-hidden="true"
                />
                <span class="type-filter-label">{{ option.label }}</span>
                <span class="sort-count">{{ option.count }}</span>
            </button>
        </div>

        <!-- Сброс стоит вплотную к сортировке, а не через отступ между группами -->
        <div v-if="showSort || canReset" class="sort-tail">
            <div
                v-if="showSort"
                class="sort-group"
                role="group"
                aria-label="Сортировка списков"
            >
                <button
                    v-for="option in sortOptions"
                    :key="option.field"
                    class="sort-btn"
                    :class="{ active: sort.field === option.field }"
                    :aria-pressed="sort.field === option.field"
                    :aria-label="sortAriaLabel(option.field, option.label)"
                    @click="emit('sort', option.field)"
                >
                    <span>{{ option.label }}</span>
                    <template v-if="sort.field === option.field">
                        <ArrowUp v-if="sort.direction === 'asc'" :size="12" />
                        <ArrowDown v-else :size="12" />
                    </template>
                </button>
            </div>

            <button
                v-if="canReset"
                class="reset-btn"
                aria-label="Сбросить поиск, фильтр и сортировку"
                title="Сбросить поиск, фильтр и сортировку"
                @click="emit('reset')"
            >
                <FunnelX :size="16" aria-hidden="true" />
            </button>
        </div>

        <slot />
    </div>
</template>

<style scoped lang="scss">
.sort-bar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px 20px;
    margin: -10px 0 18px;
}

.sort-group {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

// Между сортировкой и сбросом тот же промежуток, что между кнопками
// в группе: 8px до круга, который подсвечивается у иконки при наведении
.sort-tail {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
}

// Кнопки из слота оформляются так же, как кнопки панели
.sort-count,
:slotted(.sort-count) {
    font-size: 11px;
    opacity: 0.6;
}

.sort-btn,
:slotted(.sort-btn) {
    display: flex;
    align-items: center;
    gap: 4px;
    background: var(--surface-solid);
    color: var(--ink-soft);
    border: 1.5px solid var(--surface-border);
    padding: 5px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    font-family: inherit;
    line-height: 1.5;
    white-space: nowrap;
    cursor: pointer;
    transition: all 0.2s ease;

    &:hover {
        color: var(--brand-violet);
        border-color: var(--brand-violet);
    }

    &.active {
        color: var(--brand-violet);
        border-color: var(--brand-violet);
        background: var(--tint);
    }
}

// Только иконка, без рамки: служебное действие не путается с фильтрами.
// Иконка лежит прямо на фоне, поэтому цвет берётся от фона приложения
.reset-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    padding: 0;
    border: none;
    border-radius: 50%;
    background: none;
    color: var(--app-ink-soft, var(--ink-soft));
    cursor: pointer;
    transition:
        color 0.2s ease,
        background 0.2s ease;

    &:hover,
    &:focus-visible {
        color: var(--brand-violet);
        background: var(--tint);
    }
}

// Узкий экран: открытое поле занимает всю строку над фильтрами
@media (max-width: 599px) {
    .sort-bar .card-search.open {
        flex-basis: 100%;
        width: 100%;
    }
}

.type-filter-icon {
    display: none;
    flex-shrink: 0;
}

/* Очень узкий экран: у кнопок фильтра по типу иконка вместо подписи,
   чтобы ряд занимал меньше места */
@media (max-width: 399px) {
    .type-filter-icon {
        display: block;
    }
    .type-filter-label {
        display: none;
    }
}
</style>
