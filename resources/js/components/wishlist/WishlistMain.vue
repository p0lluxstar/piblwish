<script setup lang="ts">
import { Archive, ArrowLeft, Plus, RefreshCcw, Trash2 } from '@lucide/vue';
import { useMutation, useQueryClient } from '@tanstack/vue-query';
import { isAxiosError } from 'axios';
import { computed, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';

import { useCardPaging } from '@/composables/useCardPaging';
import { useCardSearch } from '@/composables/useCardSearch';
import { useMotivationalPhrase } from '@/composables/useMotivationalPhrase';
import {
    useArchivedCount,
    useDeleteArchivedWishlists,
    useUserWishlists,
    useWishlistArchive,
} from '@/composables/useWishlistArchive';
import {
    useWishlistSort,
    type WishlistSortField,
} from '@/composables/useWishlistSort';
import { useWishlistTypeFilter } from '@/composables/useWishlistTypeFilter';
import { api } from '@/lib/api';
import { useAuthStore } from '@/stores/auth';
import type { ApiErrorResponse } from '@/types/api';

import type {
    Wishlist,
    WishlistCreatePayload,
    WishlistItem,
    WishlistItemCheckedBy,
    WishlistType,
    WishlistUpdatePayload,
} from '../../types/wishlist';
import ActionToast from '../ui/ActionToast.vue';
import LaoderPageSpinner from '../ui/LaoderPageSpinner.vue';
import ShowMoreButtons from '../ui/ShowMoreButtons.vue';
import WishlistArchiveClearModal from './WishlistArchiveClearModal.vue';
import WishlistCard from './WishlistCard.vue';
import WishlistCardPreview from './WishlistCardPreview.vue';
import WishlistCreateModal from './WishlistCreateModal.vue';
import WishlistDeleteModal from './WishlistDeleteModal.vue';
import WishlistEditModal from './WishlistEditModal.vue';
import WishlistSortBar from './WishlistSortBar.vue';

const props = defineProps<{
    // Архив (/dashboard/archive): списки только для просмотра, без создания
    archived?: boolean;
}>();

const queryClient = useQueryClient();
const router = useRouter();
const auth = useAuthStore();
const isCreateModalOpen = ref(false);
// Список, с которого делается дубликат; null — создание с пустой формы
const duplicateSource = ref<Wishlist | null>(null);
const isEditModalOpen = ref(false);
const selectedWishlist = ref<Wishlist | null>(null);
const isDeleteModalOpen = ref(false);
const wishlistToDelete = ref<Wishlist | null>(null);
// Карточка, открытая в окне просмотра. Хранится id, а сам список берётся
// из кэша: отметки и правки сразу видны в окне
const previewWishlistId = ref<string | null>(null);
// Фраза показывается в шапке под названием сайта
const { generatePhrase: generateRandomPhrase } = useMotivationalPhrase();

const { data, isLoading, isFetching, error } = useUserWishlists(props.archived);

const wishLists = computed(() => data.value ?? []);

// Число списков в архиве для ссылки «Архив»
const archivedCount = useArchivedCount();

// В архиве «По дате» означает время переноса в архив, а сортировки по сроку нет:
// карточка архива не показывает срок. Сортировка и фильтр архива хранятся
// отдельно от основного раздела
const {
    sort,
    setSort,
    isDefaultSort,
    resetSort,
    sortedItems: sortedWishlists,
} = useWishlistSort(wishLists, {
    storageKey: props.archived ? 'wishlists-archive-sort' : 'wishlists-sort',
    fields: props.archived ? ['date', 'title'] : ['date', 'due', 'title'],
    dateOf: (wishlist): string =>
        (props.archived ? wishlist.archivedAt : wishlist.createdAt) ?? '',
    dueDateOf: (wishlist): string => wishlist.dueDate ?? '',
    // У заметки нет названия: при сортировке по названию учитывается её текст
    titleOf: (wishlist): string => wishlist.title ?? wishlist.content ?? '',
});

const {
    typeFilter,
    typeFilterOptions,
    hasTypeFilter,
    activeTypeFilter,
    filterByType,
} = useWishlistTypeFilter(wishLists, {
    storageKey: props.archived
        ? 'wishlists-archive-type-filter'
        : 'wishlists-type-filter',
    // Список без типа считается списком желаний, как и на бэкенде
    typeOf: (wishlist): WishlistType => wishlist.type ?? 'gift',
});

// У заметки нет названия: ищется её текст
const { searchQuery, hasSearch, activeSearchQuery, filterBySearch } =
    useCardSearch(wishLists, (wishlist) => [wishlist.title, wishlist.content]);

const filteredWishlists = computed(() =>
    filterBySearch(filterByType(sortedWishlists.value)),
);

// Кнопка «Сбросить»: пустой запрос, все типы и сортировка раздела по умолчанию
const resetFilters = (): void => {
    searchQuery.value = '';
    typeFilter.value = 'all';
    resetSort();
};

// Ссылка в архив — при непустом архиве. Ссылка из архива — при непустом архиве:
// у пустого архива переход есть в пояснении на месте карточек
const hasSectionLink = computed(() =>
    props.archived ? wishLists.value.length > 0 : archivedCount.value > 0,
);

const {
    visibleItems: visibleWishlists,
    hiddenCount,
    nextCount,
    showMore,
    showAll,
} = useCardPaging(filteredWishlists, [sort, typeFilter, activeSearchQuery]);

const SORT_OPTIONS: { field: WishlistSortField; label: string }[] = [
    // В архиве дата — время переноса в архив, подпись называет это явно
    {
        field: 'date',
        label: props.archived ? 'По дате архивации' : 'По дате',
    },
    ...(props.archived ? [] : [{ field: 'due' as const, label: 'По сроку' }]),
    { field: 'title', label: 'По названию' },
];

const previewWishlist = computed(
    () =>
        wishLists.value.find(
            (wishlist) => wishlist.id === previewWishlistId.value,
        ) ?? null,
);

// Список удалён: окно просмотра закрывается
watch(previewWishlist, (wishlist) => {
    if (!wishlist) previewWishlistId.value = null;
});

const openPreview = (wishlist: Wishlist): void => {
    previewWishlistId.value = wishlist.id;
};

const closePreview = (): void => {
    previewWishlistId.value = null;
};

// Окна редактирования и создания открываются вместо просмотра, а не поверх него
const editFromPreview = (wishlist: Wishlist): void => {
    closePreview();
    openEditModal(wishlist);
};

const duplicateFromPreview = (wishlist: Wishlist): void => {
    closePreview();
    openDuplicateModal(wishlist);
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
    createErrorMessage.value = null;
    duplicateSource.value = null;
};

// Текст ошибки сервера для окна: первая ошибка валидации (422). Общий message
// не используется: Laravel дописывает к нему английское «(and N more errors)»,
// а у ошибок 5xx он и вовсе «Server Error». В остальных случаях, в том числе
// при потере соединения, выводится общий текст
const getErrorMessage = (error: unknown): string => {
    const errors = isAxiosError<ApiErrorResponse>(error)
        ? error.response?.data?.data?.errors
        : null;

    return (
        Object.values(errors ?? {})[0]?.[0] ??
        'Не удалось сохранить список. Попробуйте ещё раз.'
    );
};

const createErrorMessage = ref<string | null>(null);
const updateErrorMessage = ref<string | null>(null);

const createWishlistRequest = async (
    payload: WishlistCreatePayload,
): Promise<Wishlist> => {
    const response = await api.post<{ data: Wishlist }>(
        '/v1/wishlists',
        payload,
    );

    return response.data.data;
};

const { mutate: createWishlist, isPending: isCreating } = useMutation({
    mutationFn: createWishlistRequest,

    onMutate: () => {
        createErrorMessage.value = null;
    },

    onSuccess: async (created) => {
        // Сервер уже увеличил счётчик статистики; локальная копия обновляется,
        // чтобы окно настроек показывало его без перезапроса пользователя
        if (auth.user?.stats) {
            auth.user.stats[created.type] += 1;
        }

        await queryClient.invalidateQueries({
            queryKey: ['wishlists'],
        });

        closeCreateModal();
        generateRandomPhrase();

        // Дубликат из архива создаётся среди активных списков, а не в архиве
        if (props.archived) {
            showToast({
                message: 'Копия создана в «Моих карточках»',
                actionLabel: 'Перейти',
                onAction: () => void router.push({ name: 'dashboard' }),
            });
        }
    },

    onError: (error) => {
        console.error('Ошибка создания списка', error);
        createErrorMessage.value = getErrorMessage(error);
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
    updateErrorMessage.value = null;
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
// Список из ответа сервера записывается в кэш дашборда
const setCachedWishlist = (updated: Wishlist): void => {
    queryClient.setQueryData<Wishlist[]>(['wishlists'], (oldData) => {
        if (!oldData) return [];

        return oldData.map((wishlist) =>
            wishlist.id === updated.id ? { ...wishlist, ...updated } : wishlist,
        );
    });
};

const { mutate: updateWishlist, isPending: isUpdating } = useMutation({
    mutationFn: updateWishlistRequest,

    onMutate: () => {
        updateErrorMessage.value = null;
    },

    onSuccess: (updated) => {
        setCachedWishlist(updated);
        // Окно остаётся открытым для следующих правок: форма заполняется
        // сохранённым списком, и новые позиции получают id
        selectedWishlist.value = JSON.parse(JSON.stringify(updated));
        generateRandomPhrase();
    },

    onError: (error) => {
        console.error('Ошибка обновления списка', error);
        updateErrorMessage.value = getErrorMessage(error);
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

// Отметка позиции в кэше дашборда без перезапроса списков. Вместе с отметкой
// записывается её автор (checkedBy), как его сохранит сервер: отметку ставит
// владелец, а при снятии отметки автора нет
const setCachedItemSelected = (
    { wishlistId, itemId, isSelected }: ToggleItemPayload,
    checkedBy: WishlistItemCheckedBy | null = isSelected
        ? { guest: false, name: null }
        : null,
): void => {
    queryClient.setQueryData<Wishlist[]>(['wishlists'], (oldData) =>
        oldData?.map((wishlist) =>
            wishlist.id === wishlistId
                ? {
                      ...wishlist,
                      items: wishlist.items.map((item) =>
                          item.id === itemId
                              ? { ...item, isSelected, checkedBy }
                              : item,
                      ),
                  }
                : wishlist,
        ),
    );
};

// Автор отметки позиции в кэше до её изменения: при ошибке он восстанавливается,
// чтобы не потерять имя гостя, отметку которого владелец пытался снять
const getCachedCheckedBy = ({
    wishlistId,
    itemId,
}: ToggleItemPayload): WishlistItemCheckedBy | null =>
    queryClient
        .getQueryData<Wishlist[]>(['wishlists'])
        ?.find((wishlist) => wishlist.id === wishlistId)
        ?.items.find((item) => item.id === itemId)?.checkedBy ?? null;

// Отметка дела с карточки: галочка ставится сразу, не дожидаясь ответа сервера.
// Ответ сервером в кэш не записывается: при быстрых повторных кликах ответ
// на более ранний запрос перезаписал бы последнюю отметку.
// При ошибке возвращается прежнее состояние позиции
const { mutate: toggleItemMutation } = useMutation({
    mutationFn: toggleItemRequest,

    onMutate: (payload) => {
        const previousCheckedBy = getCachedCheckedBy(payload);

        setCachedItemSelected(payload);

        return { previousCheckedBy };
    },

    onError: (error, payload, context) => {
        console.error('Ошибка отметки дела', error);

        setCachedItemSelected(
            { ...payload, isSelected: !payload.isSelected },
            context?.previousCheckedBy ?? null,
        );
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

type NoteContentPayload = {
    wishlistId: string;
    content: string;
    // Текст до изменения: возвращается в кэш, если сохранить не удалось
    previousContent: string | null;
};

const updateNoteContentRequest = async ({
    wishlistId,
    content,
}: NoteContentPayload): Promise<void> => {
    await api.patch(`/v1/wishlists/${wishlistId}`, { content });
};

const setCachedNoteContent = (
    wishlistId: string,
    content: string | null,
): void => {
    queryClient.setQueryData<Wishlist[]>(['wishlists'], (oldData) =>
        oldData?.map((wishlist) =>
            wishlist.id === wishlistId ? { ...wishlist, content } : wishlist,
        ),
    );
};

// Текст заметки, изменённый на карточке, сразу записывается в кэш. Ответ
// сервера в кэш не записывается по той же причине, что и при отметке дела:
// ответ на более ранний запрос перезаписал бы текст, набранный позже
const { mutate: updateNoteContentMutation } = useMutation({
    mutationFn: updateNoteContentRequest,

    onMutate: ({ wishlistId, content }) => {
        setCachedNoteContent(wishlistId, content);
    },

    onError: (error, { wishlistId, previousContent }) => {
        console.error('Ошибка сохранения заметки', error);

        setCachedNoteContent(wishlistId, previousContent);
    },
});

const updateNoteContent = (wishlist: Wishlist, content: string): void => {
    updateNoteContentMutation({
        wishlistId: wishlist.id,
        content,
        previousContent: wishlist.content ?? null,
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

// Уведомление внизу страницы; id пересоздаёт компонент, и таймер
// закрытия нового уведомления начинается заново
type Toast = {
    id: number;
    message: string;
    tone?: 'default' | 'error';
    actionLabel?: string;
    onAction?: () => void;
};

const toast = ref<Toast | null>(null);
let toastId = 0;

const showToast = (value: Omit<Toast, 'id'>): void => {
    toastId += 1;
    toast.value = { id: toastId, ...value };
};

const { moveWishlist } = useWishlistArchive();

// Перенос в архив и восстановление. Карточка сразу исчезает из раздела,
// а уведомление позволяет отменить действие
const moveWithUndo = (wishlist: Wishlist, archive: boolean): void => {
    const isNote = wishlist.type === 'note';
    const isFund = wishlist.type === 'fund';

    // «заметку», «сбор», «список» после глагола
    const subject = isNote ? 'заметку' : isFund ? 'сбор' : 'список';

    moveWishlist(wishlist, archive, {
        onError: () => {
            showToast({
                message: archive
                    ? `Не удалось перенести ${subject} в архив`
                    : `Не удалось восстановить ${subject}`,
                tone: 'error',
            });
        },
    });

    const moved = isNote
        ? 'Заметка перемещена'
        : isFund
          ? 'Сбор перемещён'
          : 'Список перемещён';
    const restored = isNote
        ? 'Заметка восстановлена'
        : isFund
          ? 'Сбор восстановлен'
          : 'Список восстановлен';

    showToast({
        message: archive ? `${moved} в архив` : restored,
        actionLabel: 'Отменить',
        onAction: () =>
            moveWishlist(wishlist, !archive, {
                onError: () => {
                    showToast({
                        message: 'Не удалось отменить действие',
                        tone: 'error',
                    });
                },
            }),
    });
};

const archiveWishlist = (wishlist: Wishlist): void => {
    moveWithUndo(wishlist, true);
};

const restoreWishlist = (wishlist: Wishlist): void => {
    moveWithUndo(wishlist, false);
};

// Очистка архива: удаляются все карточки архива, а не только видимые
// с текущим фильтром
const isClearArchiveModalOpen = ref(false);
const { deleteArchived, isPending: isClearingArchive } =
    useDeleteArchivedWishlists();

const clearArchive = (): void => {
    deleteArchived(
        wishLists.value.map((wishlist) => wishlist.id),
        {
            onSuccess: () => {
                isClearArchiveModalOpen.value = false;
                showToast({ message: 'Архив очищен' });
            },
            onError: () => {
                isClearArchiveModalOpen.value = false;
                showToast({
                    message: 'Не удалось очистить архив',
                    tone: 'error',
                });
            },
        },
    );
};

onMounted(generateRandomPhrase);
</script>

<template>
    <!-- Блок "Мои карточки" всегда отображается -->
    <div class="top">
        <!-- Количество карточек показано в кнопках фильтра по типу -->
        <div class="heading">{{ archived ? 'Архив' : 'Мои карточки' }}</div>
        <div v-if="wishLists.length > 0 || isLoading" class="flex gap-2">
            <!-- В архиве карточки не создаются: дубликат попадает в «Мои карточки» -->
            <button
                v-if="!archived"
                class="add-btn"
                aria-label="Новая карточка"
                @click="openCreateModal"
            >
                <Plus :size="12" />
                <span class="btn-text">Новая карточка</span>
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

    <!-- Сортировка имеет смысл, только когда списков больше одного.
         Ссылка между разделами видна и при одной карточке -->
    <WishlistSortBar
        v-if="!isLoading && (wishLists.length > 1 || hasSectionLink)"
        v-model:search="searchQuery"
        :show-search="hasSearch"
        search-placeholder="Поиск по названию"
        :type-options="typeFilterOptions"
        :active-type="activeTypeFilter"
        :show-type-filter="hasTypeFilter"
        :sort-options="SORT_OPTIONS"
        :sort="sort"
        :is-default-sort="isDefaultSort"
        :show-sort="wishLists.length > 1"
        @update:type="typeFilter = $event"
        @sort="setSort"
        @reset="resetFilters"
    >
        <!-- Переход между активными списками и архивом, в архиве справа
             от него — удаление всех карточек архива -->
        <div v-if="hasSectionLink" class="section-actions">
            <router-link
                :to="{ name: archived ? 'dashboard' : 'dashboard-archive' }"
                class="sort-btn section-link"
            >
                <template v-if="archived">
                    <ArrowLeft :size="12" aria-hidden="true" />
                    <span>Мои карточки</span>
                </template>
                <template v-else>
                    <Archive :size="12" aria-hidden="true" />
                    <span>Архив</span>
                    <span class="sort-count">{{ archivedCount }}</span>
                </template>
            </router-link>
            <button
                v-if="archived"
                class="sort-btn clear-btn"
                @click="isClearArchiveModalOpen = true"
            >
                <Trash2 :size="12" aria-hidden="true" />
                <span>Удалить все</span>
            </button>
        </div>
    </WishlistSortBar>

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
        v-else-if="wishLists.length === 0 && archived"
        class="flex flex-col items-center justify-center min-h-[300px] text-center"
    >
        <p class="empty-text text-lg mb-1">В архиве пока пусто</p>
        <p class="empty-hint mb-4">
            Сюда попадают карточки, которые вы перенесли в архив
        </p>
        <router-link :to="{ name: 'dashboard' }" class="add-btn px-6 py-2">
            <ArrowLeft :size="14" class="mr-1.5" />
            Мои карточки
        </router-link>
    </div>

    <div
        v-else-if="wishLists.length === 0"
        class="flex flex-col items-center justify-center min-h-[300px] text-center"
    >
        <p class="empty-text text-lg mb-4">У вас пока нет карточек</p>
        <button class="add-btn px-6 py-2" @click="openCreateModal">
            <Plus :size="14" class="mr-1.5" />
            Создать карточку
        </button>
    </div>

    <!-- Пустой результат возможен только из-за поиска: фильтр по типу
         показывает лишь типы, у которых есть карточки -->
    <div
        v-else-if="filteredWishlists.length === 0"
        class="flex flex-col items-center justify-center min-h-[300px] text-center"
    >
        <p class="empty-text text-lg mb-1">Ничего не найдено</p>
        <p class="empty-hint mb-4">
            Проверьте запрос или поищите среди всех типов
        </p>
        <button class="update-btn" @click="resetFilters">
            Сбросить поиск и фильтр
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
                @archive="archiveWishlist"
                @restore="restoreWishlist"
                @toggle-item="toggleItem"
                @update-content="updateNoteContent"
                @expand="openPreview"
            />
        </div>

        <ShowMoreButtons
            :hidden-count="hiddenCount"
            :next-count="nextCount"
            @more="showMore"
            @all="showAll"
        />
    </template>

    <!-- Перед остальными окнами: подтверждение удаления открывается поверх просмотра -->
    <WishlistCardPreview
        v-if="previewWishlist"
        :wishlist="previewWishlist"
        @close="closePreview"
        @edit="editFromPreview"
        @duplicate="duplicateFromPreview"
        @delete="openDeleteModal"
        @archive="archiveWishlist"
        @restore="restoreWishlist"
        @toggle-item="toggleItem"
        @update-content="updateNoteContent"
    />

    <WishlistCreateModal
        v-if="isCreateModalOpen"
        :is-pending="isCreating"
        :error-message="createErrorMessage"
        :source="duplicateSource"
        @close="closeCreateModal"
        @create="createWishlist"
    />

    <WishlistEditModal
        v-if="isEditModalOpen && selectedWishlist"
        :wishlist="selectedWishlist"
        :is-pending="isUpdating"
        :error-message="updateErrorMessage"
        @close="closeEditModal"
        @update="updateWishlist"
        @selection-cleared="setCachedWishlist"
    />

    <WishlistDeleteModal
        v-if="isDeleteModalOpen && wishlistToDelete"
        :wishlist="wishlistToDelete"
        :is-pending="isDeleting"
        @close="closeDeleteModal"
        @confirm="deleteWishlist(wishlistToDelete.id)"
    />

    <WishlistArchiveClearModal
        v-if="isClearArchiveModalOpen"
        :count="wishLists.length"
        :is-pending="isClearingArchive"
        @close="isClearArchiveModalOpen = false"
        @confirm="clearArchive"
    />

    <ActionToast
        v-if="toast"
        :key="toast.id"
        :message="toast.message"
        :tone="toast.tone"
        :action-label="toast.actionLabel"
        @action="toast.onAction?.()"
        @close="toast = null"
    />
</template>

<style scoped lang="scss">
.top {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    margin-bottom: 20px;
}

// Заголовок прижат к верху блока: без line-height: 1 над буквами
// оставалось бы место от межстрочного интервала
.heading {
    align-self: flex-start;
    font-size: 26px;
    font-weight: 800;
    line-height: 1;
    color: var(--app-ink, var(--ink));
    letter-spacing: -0.03em;
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

/* Узкий экран: кнопки «Новая карточка» и «Обновить» — круглые, только с иконками.
   Кнопка «Создать карточку» (нет ни одной карточки) не затрагивается: она вне .top */
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

// Текст лежит прямо на фоне, поэтому цвет зависит от выбранного фона приложения
.empty-text {
    color: var(--app-ink-soft, #6b7280);
}

.empty-hint {
    font-size: 14px;
    color: var(--app-ink-soft, #6b7280);
}

// Ссылка между разделами и «Удалить все» — в конце панели, справа от сортировки
.section-actions {
    display: flex;
    gap: 8px;
    margin-left: auto;
}

.section-link {
    text-decoration: none;
}

// Удаление всех карточек архива: в розовых тонах кнопки подтверждения удаления.
// Двойной класс перебивает оформление .sort-btn из WishlistSortBar
.sort-btn.clear-btn {
    color: #ec4899;
    border-color: rgba(236, 72, 153, 0.35);

    &:hover {
        color: #ec4899;
        border-color: #ec4899;
    }
}

.grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    gap: 20px;
}
</style>
