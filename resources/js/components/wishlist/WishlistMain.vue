<script setup lang="ts">
import { ArrowDown, ArrowUp, Plus, RefreshCcw } from '@lucide/vue';
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query';
import { computed, onMounted, ref, watch } from 'vue';

import {
    useWishlistSort,
    type WishlistSortField,
} from '@/composables/useWishlistSort';
import { MOTIVATIONAL_PHRASES } from '@/constants/phrases';
import { api } from '@/lib/api';

import type {
    Wishlist,
    WishlistForm,
    WishlistItem,
    WishlistType,
    WishlistUpdatePayload,
} from '../../types/wishlist';
import LaoderPageSpinner from '../ui/LaoderPageSpinner.vue';
import WishlistCard from './WishlistCard.vue';
import WishlistCreateModal from './WishlistCreateModal.vue';
import WishlistDeleteModal from './WishlistDeleteModal.vue';
import WishlistEditModal from './WishlistEditModal.vue';

const queryClient = useQueryClient();
const isCreateModalOpen = ref(false);
// Список, с которого делается дубликат; null — создание с пустой формы
const duplicateSource = ref<Wishlist | null>(null);
const isEditModalOpen = ref(false);
const selectedWishlist = ref<Wishlist | null>(null);
const isDeleteModalOpen = ref(false);
const wishlistToDelete = ref<Wishlist | null>(null);
const randomPhrase = ref('');

// Фраза меняется при загрузке страницы и после действий пользователя: «Обновить»,
// создание, изменение и удаление списка. Таймер не используется, чтобы движение
// в заголовке не отвлекало. Подряд одна и та же фраза не выпадает
const generateRandomPhrase = (): void => {
    const candidates = MOTIVATIONAL_PHRASES.filter(
        (phrase) => phrase !== randomPhrase.value,
    );
    const pool = candidates.length > 0 ? candidates : MOTIVATIONAL_PHRASES;

    randomPhrase.value = pool[Math.floor(Math.random() * pool.length)];
};

const fetchWishlists = async (): Promise<Wishlist[]> => {
    const response = await api.get<{ data: Wishlist[] }>('/v1/wishlists');

    return response.data.data;
};

const { data, isLoading, isFetching, error } = useQuery({
    queryKey: ['wishlists'],
    queryFn: fetchWishlists,
});

const wishLists = computed(() => data.value ?? []);

const { sort, setSort, sortedWishlists } = useWishlistSort(wishLists);

type WishlistTypeFilter = 'all' | WishlistType;

const TYPE_FILTER_STORAGE_KEY = 'wishlists-type-filter';

// Чтение защищено так же, как у сортировки: localStorage может быть недоступен
const readTypeFilter = (): WishlistTypeFilter => {
    try {
        const value = window.localStorage.getItem(TYPE_FILTER_STORAGE_KEY);

        if (value === 'gift' || value === 'todo') return value;
    } catch {
        // Значение недоступно — показываются все списки
    }

    return 'all';
};

const typeFilter = ref<WishlistTypeFilter>(readTypeFilter());

watch(typeFilter, (value) => {
    try {
        window.localStorage.setItem(TYPE_FILTER_STORAGE_KEY, value);
    } catch {
        // Сохранение недоступно — фильтр действует до перезагрузки страницы
    }
});

const typeCounts = computed(() => ({
    all: wishLists.value.length,
    gift: wishLists.value.filter((wishlist) => wishlist.type !== 'todo').length,
    todo: wishLists.value.filter((wishlist) => wishlist.type === 'todo').length,
}));

// Фильтр нужен, только когда есть списки обоих типов. Иначе он скрыт и не
// действует: сохранённый фильтр не должен прятать все карточки без возможности
// его сбросить
const hasTypeFilter = computed(
    () => typeCounts.value.gift > 0 && typeCounts.value.todo > 0,
);

const filteredWishlists = computed(() => {
    if (!hasTypeFilter.value || typeFilter.value === 'all') {
        return sortedWishlists.value;
    }

    return sortedWishlists.value.filter((wishlist) =>
        typeFilter.value === 'todo'
            ? wishlist.type === 'todo'
            : wishlist.type !== 'todo',
    );
});

const TYPE_FILTER_OPTIONS: { value: WishlistTypeFilter; label: string }[] = [
    { value: 'all', label: 'Все' },
    { value: 'gift', label: 'Желания' },
    { value: 'todo', label: 'Дела' },
];

// Карточки выводятся порциями: все списки уже загружены, ограничивается только отрисовка.
// Следующая порция добавляется кнопкой «Показать ещё»
const PAGE_SIZE = 12;
const visibleCount = ref(PAGE_SIZE);

const visibleWishlists = computed(() =>
    filteredWishlists.value.slice(0, visibleCount.value),
);
const hiddenCount = computed(() =>
    Math.max(filteredWishlists.value.length - visibleCount.value, 0),
);

const showMore = (): void => {
    visibleCount.value += PAGE_SIZE;
};

// После смены сортировки или фильтра набор карточек другой, поэтому вывод
// начинается с первой порции
watch([sort, typeFilter], () => {
    visibleCount.value = PAGE_SIZE;
});

const SORT_OPTIONS: { field: WishlistSortField; label: string }[] = [
    { field: 'date', label: 'По дате' },
    { field: 'title', label: 'По названию' },
];

// Подпись для экранных дикторов: поле и текущее направление сортировки
const sortAriaLabel = (field: WishlistSortField, label: string): string => {
    if (sort.value.field !== field) return `Сортировать ${label.toLowerCase()}`;

    return `${label}, ${sort.value.direction === 'asc' ? 'по возрастанию' : 'по убыванию'}`;
};

const openCreateModal = (): void => {
    duplicateSource.value = null;
    isCreateModalOpen.value = true;
};

// Модалка создания с формой, заполненной значениями выбранного списка
const openDuplicateModal = (wishlist: Wishlist): void => {
    duplicateSource.value = JSON.parse(JSON.stringify(wishlist));
    isCreateModalOpen.value = true;
};

const closeCreateModal = (): void => {
    isCreateModalOpen.value = false;
    duplicateSource.value = null;
};

const createWishlistRequest = async (
    payload: WishlistForm,
): Promise<Wishlist> => {
    const response = await api.post<{ data: Wishlist }>(
        '/v1/wishlists',
        payload,
    );

    return response.data.data;
};

const { mutate: createWishlist, isPending: isCreating } = useMutation({
    mutationFn: createWishlistRequest,

    onSuccess: async () => {
        await queryClient.invalidateQueries({
            queryKey: ['wishlists'],
        });

        closeCreateModal();
        generateRandomPhrase();
    },

    onError: (error) => {
        console.error('Ошибка создания списка', error);
    },
});

const openEditModal = (wishlist: Wishlist): void => {
    console.log(
        'Открытие модального окна редактирования для списка:',
        wishlist,
    );
    selectedWishlist.value = JSON.parse(JSON.stringify(wishlist));
    isEditModalOpen.value = true;
};

const closeEditModal = (): void => {
    isEditModalOpen.value = false;
    selectedWishlist.value = null;
};

const updateWishlistRequest = async ({
    id,
    ...payload
}: WishlistUpdatePayload): Promise<Wishlist> => {
    const response = await api.patch<{ data: Wishlist }>(
        `/v1/wishlists/${id}`,
        payload,
    );

    return response.data.data;
};

// Цепочка обновления списка: emit('update') → мутация (PATCH) → queryClient.setQueryData() с ответом сервера → computed wishLists пересчитывается → WishlistCard получает новые props
const { mutate: updateWishlist, isPending: isUpdating } = useMutation({
    mutationFn: updateWishlistRequest,

    onSuccess: (updated) => {
        queryClient.setQueryData<Wishlist[]>(['wishlists'], (oldData) => {
            if (!oldData) return [];

            return oldData.map((wishlist) =>
                wishlist.id === updated.id
                    ? { ...wishlist, ...updated }
                    : wishlist,
            );
        });

        closeEditModal();
        generateRandomPhrase();
    },

    onError: (error) => {
        console.error('Ошибка обновления списка', error);
    },
});

type ToggleItemPayload = {
    wishlistId: string;
    itemId: string;
    isSelected: boolean;
};

const toggleItemRequest = async ({
    wishlistId,
    itemId,
    isSelected,
}: ToggleItemPayload): Promise<Wishlist> => {
    const response = await api.patch<{ data: Wishlist }>(
        `/v1/wishlists/${wishlistId}/items/${itemId}`,
        { isSelected },
    );

    return response.data.data;
};

// Отметка позиции в кэше дашборда без перезапроса списков
const setCachedItemSelected = ({
    wishlistId,
    itemId,
    isSelected,
}: ToggleItemPayload): void => {
    queryClient.setQueryData<Wishlist[]>(['wishlists'], (oldData) =>
        oldData?.map((wishlist) =>
            wishlist.id === wishlistId
                ? {
                      ...wishlist,
                      items: wishlist.items.map((item) =>
                          item.id === itemId ? { ...item, isSelected } : item,
                      ),
                  }
                : wishlist,
        ),
    );
};

// Отметка дела с карточки: галочка ставится сразу, не дожидаясь ответа сервера.
// Ответ сервером в кэш не записывается: при быстрых повторных кликах ответ
// на более ранний запрос перезаписал бы последнюю отметку.
// При ошибке возвращается прежнее состояние позиции
const { mutate: toggleItemMutation } = useMutation({
    mutationFn: toggleItemRequest,

    onMutate: setCachedItemSelected,

    onError: (error, payload) => {
        console.error('Ошибка отметки дела', error);

        setCachedItemSelected({ ...payload, isSelected: !payload.isSelected });
    },
});

const toggleItem = (wishlist: Wishlist, item: WishlistItem): void => {
    if (!item.id) return;

    toggleItemMutation({
        wishlistId: wishlist.id,
        itemId: item.id,
        isSelected: !item.isSelected,
    });
};

const updateWishlists = async (): Promise<void> => {
    await queryClient.invalidateQueries({
        queryKey: ['wishlists'],
    });
    generateRandomPhrase();
};

const deleteWishlistRequest = async (id: string): Promise<void> => {
    await api.delete(`/v1/wishlists/${id}`);
};

const { mutate: deleteWishlist, isPending: isDeleting } = useMutation({
    mutationFn: deleteWishlistRequest,
    onSuccess: async () => {
        await queryClient.invalidateQueries({
            queryKey: ['wishlists'],
        });
        closeDeleteModal(); // Закрываем модальное окно при успехе
        generateRandomPhrase();
    },
    onError: (error) => {
        console.error('Ошибка удаления списка', error);
    },
});

const openDeleteModal = (wishlist: Wishlist): void => {
    wishlistToDelete.value = wishlist;
    isDeleteModalOpen.value = true;
};
const closeDeleteModal = (): void => {
    isDeleteModalOpen.value = false;
    wishlistToDelete.value = null;
};

onMounted(generateRandomPhrase);
</script>

<template>
    <!-- Блок "Мои списки" всегда отображается -->
    <div class="top">
        <div>
            <div class="heading">Мои списки</div>
            <div class="sub">
                <span v-if="!isLoading">
                    <span>Списков {{ wishLists.length }}</span>
                    <!-- Отступы вокруг точки заданы в CSS: пробелы между тегами Vue удаляет -->
                    <span class="separator">·</span>
                    <!-- Ключ пересоздаёт элемент при смене фразы, чтобы анимация срабатывала заново -->
                    <span :key="randomPhrase" class="phrase-wrapper">
                        <span class="phrase">{{ randomPhrase }}</span>
                    </span>
                </span>
                <span v-else>Загрузка списков...</span>
            </div>
        </div>
        <div v-if="wishLists.length > 0 || isLoading" class="flex gap-2">
            <button
                class="add-btn"
                aria-label="Новый список"
                @click="openCreateModal"
            >
                <Plus :size="12" />
                <span class="btn-text">Новый список</span>
            </button>
            <button
                class="update-btn"
                aria-label="Обновить"
                @click="updateWishlists"
            >
                <RefreshCcw :size="12" />
                <span class="btn-text">Обновить</span>
            </button>
        </div>
    </div>

    <!-- Сортировка имеет смысл, только когда списков больше одного -->
    <div v-if="!isLoading && wishLists.length > 1" class="sort-bar">
        <div
            v-if="hasTypeFilter"
            class="sort-group"
            role="group"
            aria-label="Тип списков"
        >
            <button
                v-for="option in TYPE_FILTER_OPTIONS"
                :key="option.value"
                class="sort-btn"
                :class="{ active: typeFilter === option.value }"
                :aria-pressed="typeFilter === option.value"
                @click="typeFilter = option.value"
            >
                <span>{{ option.label }}</span>
                <span class="sort-count">{{ typeCounts[option.value] }}</span>
            </button>
        </div>

        <div class="sort-group" role="group" aria-label="Сортировка списков">
            <button
                v-for="option in SORT_OPTIONS"
                :key="option.field"
                class="sort-btn"
                :class="{ active: sort.field === option.field }"
                :aria-pressed="sort.field === option.field"
                :aria-label="sortAriaLabel(option.field, option.label)"
                @click="setSort(option.field)"
            >
                <span>{{ option.label }}</span>
                <template v-if="sort.field === option.field">
                    <ArrowUp v-if="sort.direction === 'asc'" :size="12" />
                    <ArrowDown v-else :size="12" />
                </template>
            </button>
        </div>
    </div>

    <div
        v-if="isLoading || isFetching"
        class="flex items-center justify-center min-h-[400px]"
    >
        <LaoderPageSpinner />
    </div>

    <div
        v-else-if="error"
        class="flex items-center justify-center min-h-[400px]"
    >
        <p>Ошибка при загрузке</p>
    </div>

    <div
        v-else-if="wishLists.length === 0"
        class="flex flex-col items-center justify-center min-h-[300px] text-center"
    >
        <p class="text-gray-500 dark:text-gray-400 text-lg mb-4">
            У вас пока нет списков
        </p>
        <button class="add-btn px-6 py-2" @click="openCreateModal">
            <Plus :size="14" class="mr-1.5" />
            Создать список
        </button>
    </div>

    <template v-else>
        <div class="grid" id="grid">
            <WishlistCard
                v-for="wishlist in visibleWishlists"
                :key="wishlist.id"
                :wishlist="wishlist"
                @edit="openEditModal"
                @duplicate="openDuplicateModal"
                @delete="openDeleteModal"
                @toggle-item="toggleItem"
            />
        </div>

        <div v-if="hiddenCount > 0" class="more">
            <button class="more-btn" @click="showMore">
                Показать ещё
                <span class="more-count">{{ hiddenCount }}</span>
            </button>
        </div>
    </template>

    <WishlistCreateModal
        v-if="isCreateModalOpen"
        :is-pending="isCreating"
        :source="duplicateSource"
        @close="closeCreateModal"
        @create="createWishlist"
    />

    <WishlistEditModal
        v-if="isEditModalOpen && selectedWishlist"
        :wishlist="selectedWishlist"
        :is-pending="isUpdating"
        @close="closeEditModal"
        @update="updateWishlist"
    />

    <WishlistDeleteModal
        v-if="isDeleteModalOpen && wishlistToDelete"
        :wishlist="wishlistToDelete"
        :is-pending="isDeleting"
        @close="closeDeleteModal"
        @confirm="deleteWishlist(wishlistToDelete.id)"
    />
</template>

<style scoped lang="scss">
.top {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    margin-bottom: 26px;
}
.heading {
    font-size: 26px;
    font-weight: 800;
    color: var(--ink);
    letter-spacing: -0.03em;
}
.sub {
    font-size: 13px;
    color: var(--ink-soft);
    margin-top: 4px;
}
.add-btn,
.update-btn {
    display: flex;
    align-items: center;
    gap: 6px;
    background: var(--brand-gradient);
    border: none;
    color: #fff;
    padding: 9px 18px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    font-family: inherit;
    transition: all 0.2s ease;
    line-height: 1.5;
    white-space: nowrap;
    box-shadow: var(--shadow-glow);

    &:hover:not(:disabled) {
        transform: translateY(-2px) scale(1.02);
        box-shadow: var(--shadow-glow-lg);
    }

    &:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
    }
}

.update-btn {
    background: #fff;
    color: var(--brand-violet);
    border: 1.5px solid var(--surface-border);
    box-shadow: none;

    &:hover:not(:disabled) {
        box-shadow: var(--shadow-glow);
        background: #fff;
    }
}

/* Узкий экран: кнопки «Новый список» и «Обновить» — круглые, только с иконками.
   Кнопка «Создать список» (нет ни одного списка) не затрагивается: она вне .top */
@media (max-width: 599px) {
    .top .add-btn,
    .top .update-btn {
        justify-content: center;
        width: 38px;
        height: 38px;
        padding: 0;

        svg {
            width: 16px;
            height: 16px;
        }

        .btn-text {
            display: none;
        }
    }
}

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

.sort-count {
    font-size: 11px;
    opacity: 0.6;
}

.sort-btn {
    display: flex;
    align-items: center;
    gap: 4px;
    background: #fff;
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
        background: rgba(139, 92, 246, 0.08);
    }
}

.separator {
    margin: 0 0.35em;
}

.grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    gap: 20px;
}

.more {
    display: flex;
    justify-content: center;
    margin-top: 28px;
}

.more-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    background: #fff;
    color: var(--brand-violet);
    border: 1.5px solid var(--surface-border);
    padding: 9px 22px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    font-family: inherit;
    line-height: 1.5;
    cursor: pointer;
    transition: all 0.2s ease;

    &:hover {
        border-color: var(--brand-violet);
        box-shadow: var(--shadow-glow);
    }
}

.more-count {
    color: var(--ink-soft);
    font-weight: 500;
}

.phrase-wrapper {
    display: inline-block;
    animation: fadeSlide 0.4s ease-out;

    @media (prefers-reduced-motion: reduce) {
        animation: none;
    }
}

.phrase {
    color: var(--brand-pink);
    font-weight: 600;
}

@keyframes fadeSlide {
    from {
        opacity: 0;
        transform: translateY(4px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Очень узкий экран: количество списков и фраза на отдельных строках, без точки.
   Медиазапрос стоит в конце, иначе .phrase-wrapper { display: inline-block } выше по файлу перекрывает его */
@media (max-width: 399px) {
    .separator {
        display: none;
    }
    .phrase-wrapper {
        display: block;
    }
}
</style>
