<script setup lang="ts">
import { ArrowDown, ArrowUp, Plus, RefreshCcw } from '@lucide/vue';
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query';
import { onMounted, ref } from 'vue';
import { computed } from 'vue';

import {
    useWishlistSort,
    type WishlistSortField,
} from '@/composables/useWishlistSort';
import { MOTIVATIONAL_PHRASES } from '@/constants/phrases';
import { api } from '@/lib/api';

import type { Wishlist, WishlistForm } from '../../types/wishlist';
import LaoderPageSpinner from '../ui/LaoderPageSpinner.vue';
import WishlistCard from './WishlistCard.vue';
import WishlistCreateModal from './WishlistCreateModal.vue';
import WishlistDeleteModal from './WishlistDeleteModal.vue';
import WishlistEditModal from './WishlistEditModal.vue';

const queryClient = useQueryClient();
const isCreateModalOpen = ref(false);
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
    isCreateModalOpen.value = true;
};

const closeCreateModal = (): void => {
    isCreateModalOpen.value = false;
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
}: WishlistForm & { id: string }): Promise<Wishlist> => {
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
    <div
        v-if="!isLoading && wishLists.length > 1"
        class="sort-bar"
        role="group"
        aria-label="Сортировка списков"
    >
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
            У вас пока нет списков желаний
        </p>
        <button class="add-btn px-6 py-2" @click="openCreateModal">
            <Plus :size="14" class="mr-1.5" />
            Создать список
        </button>
    </div>

    <div v-else class="grid" id="grid">
        <WishlistCard
            v-for="wishlist in sortedWishlists"
            :key="wishlist.id"
            :wishlist="wishlist"
            @edit="openEditModal"
            @delete="openDeleteModal"
        />
    </div>

    <WishlistCreateModal
        v-if="isCreateModalOpen"
        :is-pending="isCreating"
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
    gap: 8px;
    margin: -10px 0 18px;
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
