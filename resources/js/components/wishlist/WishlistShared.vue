<script setup lang="ts">
import { Check, Copy, ExternalLink, Gift, Save, Users, X } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import { useGuestReservations } from '@/composables/useGuestReservations';
import { api } from '@/lib/api';
import { copyToClipboard } from '@/lib/clipboard';
import { formatPrice } from '@/lib/itemPrice';
import { getItemUrlHost } from '@/lib/itemUrl';
import type {
    JointGiftDraft,
    Wishlist,
    WishlistItem,
    WishlistJointGift,
} from '@/types/wishlist';

import LaoderPageSpinner from '../ui/LaoderPageSpinner.vue';
import LoaderButtonSpinner from '../ui/LoaderButtonSpinner.vue';
import ItemPriorityHearts from './ItemPriorityHearts.vue';
import JointGiftFields from './JointGiftFields.vue';

const route = useRoute();
const router = useRouter();
const wishlist = ref<Wishlist | null>(null);
const isLoading = ref(true);
const error = ref<string | null>(null);
const wishlistId = String(route.params.id || '');
const selectedItems = ref<string[]>([]);
const isSaving = ref(false);

// Совместные подарки среди выбранных, но ещё не сохранённых позиций: ключ — id позиции.
// Позиция есть в объекте, если гость включил для неё «Дарим вместе»
const jointGiftDrafts = ref<Record<string, JointGiftDraft>>({});

const toggleJointGift = (itemId: string): void => {
    if (isBusy.value) return;

    if (jointGiftDrafts.value[itemId]) {
        delete jointGiftDrafts.value[itemId];
    } else {
        jointGiftDrafts.value[itemId] = { name: '', contact: '', comment: '' };
    }
};

// Имя организатора обязательно: без него другие гости не поймут, к кому обращаться
const hasJointGiftWithoutName = computed(() =>
    Object.values(jointGiftDrafts.value).some(
        (draft) => draft.name.trim() === '',
    ),
);

// Правка совместного подарка на уже сохранённую позицию своей брони:
// изменить данные, добавить совместный подарок или убрать его
const editingJointGiftItemId = ref<string | null>(null);
const jointGiftEditDraft = ref<JointGiftDraft>({
    name: '',
    contact: '',
    comment: '',
});
// Позиция, совместный подарок которой сейчас сохраняется
const savingJointGiftItemId = ref<string | null>(null);
// Кнопка, на которой выводится спиннер: «Сохранить» или «Не дарим вместе»
const jointGiftSaveAction = ref<'save' | 'remove' | null>(null);

const startJointGiftEdit = (item: WishlistItem): void => {
    if (!item.id || isBusy.value) return;

    editingJointGiftItemId.value = item.id;
    actionError.value = null;
    jointGiftEditDraft.value = {
        name: item.jointGift?.name ?? '',
        contact: item.jointGift?.contact ?? '',
        comment: item.jointGift?.comment ?? '',
    };
};

const stopJointGiftEdit = (): void => {
    editingJointGiftItemId.value = null;
};

// Сохранить данные из формы или, при remove, убрать совместный подарок.
// Выбор позиции в обоих случаях остаётся за гостем
const saveJointGift = async (
    item: WishlistItem,
    remove = false,
): Promise<void> => {
    const token = tokenForItem(item.id);

    if (!token || !item.id || isBusy.value) return;

    const draft = jointGiftEditDraft.value;

    if (!remove && draft.name.trim() === '') {
        actionError.value =
            'Укажите имя организатора, чтобы другие гости знали, к кому обращаться.';
        return;
    }

    savingJointGiftItemId.value = item.id;
    jointGiftSaveAction.value = remove ? 'remove' : 'save';
    actionError.value = null;

    try {
        const response = await api.put<{ data: Wishlist }>(
            `/api/v1/shared-wishlists/${wishlistId}/items/${item.id}/joint-gift`,
            {
                token,
                joint_gift: remove
                    ? null
                    : {
                          name: draft.name.trim(),
                          contact: draft.contact.trim() || null,
                          comment: draft.comment.trim() || null,
                      },
            },
        );

        wishlist.value = response.data.data;
        stopJointGiftEdit();
    } catch (saveError) {
        console.error('Ошибка сохранения совместного подарка:', saveError);
        actionError.value =
            'Не удалось сохранить изменения. Обновите страницу и попробуйте снова.';
    } finally {
        savingJointGiftItemId.value = null;
        jointGiftSaveAction.value = null;
    }
};

// Подпись под выбранной позицией: «Иван, коллега Ани · @ivan_k»
const jointGiftOrganizer = (jointGift: WishlistJointGift): string =>
    [jointGift.name, jointGift.contact].filter(Boolean).join(' · ');

const {
    tokenForItem,
    load: loadReservations,
    remember,
} = useGuestReservations(wishlistId);

// Ссылка для отмены выбора с другого устройства; показывается после сохранения
const reservationLink = ref<string | null>(null);
const linkCopyStatus = ref<'idle' | 'copied' | 'error'>('idle');
let linkCopyTimer: number | null = null;

// Позиция, выбор которой сейчас отменяется
const cancellingItemId = ref<string | null>(null);
// Ошибка сохранения или отмены выбора
const actionError = ref<string | null>(null);

// Идёт сохранение или отмена: выбор в списке временно недоступен
const isBusy = computed(
    () =>
        isSaving.value ||
        cancellingItemId.value !== null ||
        savingJointGiftItemId.value !== null,
);

// Токен брони из ссылки для отмены (?reservation=…)
const tokenFromLink = (): string | null => {
    const value = route.query.reservation;

    return typeof value === 'string' ? value : null;
};

const buildReservationLink = (token: string): string => {
    const appUrl = import.meta.env.VITE_API_URL || window.location.origin;

    return `${appUrl}/shared-wishlists/${wishlistId}?reservation=${token}`;
};

const getWishlist = async (): Promise<void> => {
    if (!wishlistId) {
        error.value = 'Не указан идентификатор списка';
        isLoading.value = false;
        return;
    }

    isLoading.value = true;
    error.value = null;

    try {
        const response = await api.get<{ data: Wishlist }>(
            `/api/v1/shared-wishlists/${wishlistId}`,
        );
        wishlist.value = response.data.data;

        // Токен из ссылки сохраняется в браузере, а из адреса убирается,
        // чтобы не остаться в истории и не уйти дальше при пересылке адреса страницы
        const linkToken = tokenFromLink();

        await loadReservations(linkToken);

        if (linkToken) {
            void router.replace({ query: {} });
        }
    } catch (fetchError) {
        console.error('Ошибка загрузки списка:', fetchError);
        error.value =
            'Не удалось загрузить список. Проверьте URL или попробуйте позже.';
        wishlist.value = null;
    } finally {
        isLoading.value = false;
        isSaving.value = false;
    }
};

const save = async (): Promise<void> => {
    if (selectedItems.value.length === 0 || isBusy.value) {
        return;
    }

    if (hasJointGiftWithoutName.value) {
        actionError.value =
            'Укажите имя организатора, чтобы другие гости знали, к кому обращаться.';
        return;
    }

    // Список остаётся на экране: спиннер выводится только на кнопке сохранения
    isSaving.value = true;
    actionError.value = null;

    try {
        const response = await api.patch<{ data: Wishlist }>(
            `/api/v1/shared-wishlists/${wishlistId}/items`,
            {
                item_ids: selectedItems.value,
                joint_gifts: Object.entries(jointGiftDrafts.value).map(
                    ([itemId, draft]) => ({
                        item_id: itemId,
                        name: draft.name.trim(),
                        contact: draft.contact.trim() || null,
                        comment: draft.comment.trim() || null,
                    }),
                ),
            },
        );

        // Обновляем список актуальными данными с сервера
        wishlist.value = response.data.data;

        const { reservation } = response.data.data;

        if (reservation) {
            remember(reservation);
            reservationLink.value = buildReservationLink(reservation.token);
            linkCopyStatus.value = 'idle';
        }

        // Очищаем локальный список выбранных элементов
        selectedItems.value = [];
        jointGiftDrafts.value = {};
    } catch (error) {
        console.error('Ошибка сохранения:', error);
        actionError.value =
            'Не удалось сохранить выбор. Обновите страницу и попробуйте снова.';
    } finally {
        isSaving.value = false;
    }
};

// Отмена выбора одной позиции; остальные подарки брони остаются за гостем
const cancelItem = async (item: WishlistItem): Promise<void> => {
    const token = tokenForItem(item.id);

    if (!token || !item.id || isBusy.value) return;

    cancellingItemId.value = item.id;
    actionError.value = null;

    try {
        const response = await api.post<{ data: Wishlist }>(
            `/api/v1/shared-wishlists/${wishlistId}/reservations/cancel`,
            { token, item_ids: [item.id] },
        );

        wishlist.value = response.data.data;

        // Выбор отменён: правка совместного подарка на эту позицию больше не нужна
        if (editingJointGiftItemId.value === item.id) {
            stopJointGiftEdit();
        }

        const { reservation } = response.data.data;

        if (reservation) {
            remember(reservation);

            // Все подарки брони отменены: ссылка для отмены больше не нужна
            if (reservation.itemIds.length === 0) {
                reservationLink.value = null;
            }
        }
    } catch (cancelRequestError) {
        console.error('Ошибка отмены выбора:', cancelRequestError);
        actionError.value =
            'Не удалось отменить выбор. Обновите страницу и попробуйте снова.';
    } finally {
        cancellingItemId.value = null;
    }
};

const copyReservationLink = async (): Promise<void> => {
    if (!reservationLink.value) return;

    linkCopyStatus.value = (await copyToClipboard(reservationLink.value))
        ? 'copied'
        : 'error';

    if (linkCopyTimer) {
        window.clearTimeout(linkCopyTimer);
    }

    linkCopyTimer = window.setTimeout(() => {
        linkCopyStatus.value = 'idle';
        linkCopyTimer = null;
    }, 2000);
};

const selectLinkInput = (event: { target: unknown }): void => {
    if (event.target instanceof window.HTMLInputElement) {
        event.target.select();
    }
};

onBeforeUnmount(() => {
    if (linkCopyTimer) {
        window.clearTimeout(linkCopyTimer);
    }
});

const hasChanges = computed(() => selectedItems.value.length > 0);

// Все подарки уже выбраны гостями: выбирать больше нечего
const allSelected = computed(() => {
    const items = wishlist.value?.items ?? [];

    return items.length > 0 && items.every((item) => item.isSelected);
});

// Порядок позиций: заданный владельцем, по приоритету (сначала «очень хочу»)
// или по стоимости
type ItemOrder = 'owner' | 'priority' | 'price-asc' | 'price-desc';

const itemOrder = ref<ItemOrder>('owner');

const hasPriorities = computed(() =>
    (wishlist.value?.items ?? []).some((item) => item.priority),
);

// Стоимость 0 ₽ тоже считается указанной
const hasPrices = computed(() =>
    (wishlist.value?.items ?? []).some((item) => item.price != null),
);

// Варианты показываются, только если владелец указал приоритет или стоимость
// хотя бы у одной позиции; без них переключатель не выводится вовсе
const itemOrderOptions = computed(() => [
    { value: 'owner' as const, label: 'По порядку' },
    ...(hasPriorities.value
        ? [{ value: 'priority' as const, label: 'По приоритету' }]
        : []),
    ...(hasPrices.value
        ? [
              { value: 'price-asc' as const, label: 'Сначала дешевле' },
              { value: 'price-desc' as const, label: 'Сначала дороже' },
          ]
        : []),
]);

// Позиции без стоимости выводятся последними при любом направлении сортировки
const comparePrice = (
    a: WishlistItem,
    b: WishlistItem,
    direction: 1 | -1,
): number => {
    if (a.price == null || b.price == null) {
        return Number(a.price == null) - Number(b.price == null);
    }

    return (a.price - b.price) * direction;
};

// Сортировка устойчива: позиции с одинаковым приоритетом или стоимостью остаются
// в порядке владельца, позиции без приоритета выводятся последними
const sortedItems = computed<WishlistItem[]>(() => {
    const items = wishlist.value?.items ?? [];

    switch (itemOrder.value) {
        case 'priority':
            return [...items].sort(
                (a, b) => (b.priority ?? 0) - (a.priority ?? 0),
            );
        case 'price-asc':
            return [...items].sort((a, b) => comparePrice(a, b, 1));
        case 'price-desc':
            return [...items].sort((a, b) => comparePrice(a, b, -1));
        default:
            return items;
    }
});

const toggleItem = (item: WishlistItem): void => {
    // Уже выбран кем-то другим или идёт сохранение — ничего не делаем
    if (item.isSelected || isBusy.value) {
        return;
    }

    const index = selectedItems.value.indexOf(item.id);

    if (index === -1) {
        selectedItems.value.push(item.id);
    } else {
        selectedItems.value.splice(index, 1);
        // Снятая позиция не может быть совместным подарком
        delete jointGiftDrafts.value[item.id];
    }
};

onMounted(getWishlist);
</script>

<template>
    <div class="wishlist-view">
        <!-- Только первая загрузка: при сохранении список остаётся на экране -->
        <div v-if="isLoading" class="loader">
            <LaoderPageSpinner />
        </div>

        <div v-else-if="error" class="error">
            <p>{{ error }}</p>
        </div>

        <div v-else-if="wishlist" class="content">
            <!-- Пояснение для гостя: чей это список подарков -->
            <section class="intro" aria-labelledby="shared-intro-title">
                <h1 id="shared-intro-title" class="intro-title">
                    {{
                        wishlist.username
                            ? `${wishlist.username} делится с вами списком подарков`
                            : 'С вами поделились списком подарков'
                    }}
                </h1>

                <p v-if="allSelected" class="intro-note">
                    Все подарки из этого списка уже выбраны
                </p>
            </section>

            <!-- После сохранения: ссылка для отмены выбора с другого устройства -->
            <div
                v-if="reservationLink"
                class="reservation-notice"
                role="status"
            >
                <button
                    class="reservation-notice-close"
                    type="button"
                    aria-label="Закрыть"
                    @click="reservationLink = null"
                >
                    <X :size="14" />
                </button>

                <p class="reservation-notice-title">Подарки выбраны</p>

                <p class="reservation-notice-text">
                    В этом браузере выбор можно отменить прямо в списке. Чтобы
                    отменить его с другого устройства, сохраните ссылку:
                </p>

                <div class="reservation-notice-link">
                    <input
                        :value="reservationLink"
                        type="text"
                        readonly
                        aria-label="Ссылка для отмены выбора"
                        @focus="selectLinkInput"
                    />

                    <button
                        :class="[
                            'reservation-notice-copy',
                            {
                                'reservation-notice-copy--error':
                                    linkCopyStatus === 'error',
                            },
                        ]"
                        type="button"
                        @click="copyReservationLink"
                    >
                        <Check v-if="linkCopyStatus === 'copied'" :size="14" />
                        <Copy v-else :size="14" />
                        {{
                            linkCopyStatus === 'copied'
                                ? 'Скопировано'
                                : linkCopyStatus === 'error'
                                  ? 'Не удалось'
                                  : 'Скопировать'
                        }}
                    </button>
                </div>
            </div>

            <p v-if="actionError" class="cancel-error" role="alert">
                {{ actionError }}
            </p>

            <div class="card">
                <div class="card-header">
                    <div class="card-author">
                        <span class="card-author-avatar">
                            {{ wishlist.username?.charAt(0).toUpperCase() }}
                        </span>
                        <span>
                            <span class="card-author-name">
                                {{ wishlist.username }}
                            </span>
                        </span>
                    </div>
                    <span class="card-title">
                        {{ wishlist.title }}
                    </span>
                </div>

                <div
                    v-if="itemOrderOptions.length > 1"
                    class="item-order"
                    role="group"
                    aria-label="Порядок подарков"
                >
                    <button
                        v-for="option in itemOrderOptions"
                        :key="option.value"
                        type="button"
                        :class="[
                            'item-order-btn',
                            {
                                'item-order-btn--active':
                                    itemOrder === option.value,
                            },
                        ]"
                        :aria-pressed="itemOrder === option.value"
                        @click="itemOrder = option.value"
                    >
                        {{ option.label }}
                    </button>
                </div>

                <template
                    v-for="(item, itemIndex) in sortedItems"
                    :key="item.id ?? itemIndex"
                >
                    <div
                        :class="[
                            'item',
                            {
                                disabled: item.isSelected,
                                'item--mine':
                                    selectedItems.includes(item.id) ||
                                    (item.isSelected && tokenForItem(item.id)),
                            },
                        ]"
                        :title="
                            item.isSelected
                                ? tokenForItem(item.id)
                                    ? 'Ваш выбор'
                                    : 'Уже выбрано'
                                : undefined
                        "
                    >
                        <!-- Позицию выбрал этот гость: серый значок подарка, выбор можно отменить кнопкой справа -->
                        <span
                            v-if="item.isSelected && tokenForItem(item.id)"
                            class="reserved-icon"
                            role="img"
                            aria-label="Ваш выбор"
                        >
                            <Gift :size="11" />
                        </span>

                        <!-- Позицию уже выбрал другой гость: серый значок подарка вместо чекбокса -->
                        <span
                            v-else-if="item.isSelected"
                            class="reserved-icon"
                            role="img"
                            aria-label="Уже выбрано"
                        >
                            <Gift :size="11" />
                        </span>

                        <label v-else class="checkbox-wrapper">
                            <input
                                type="checkbox"
                                class="checkbox-input"
                                :checked="selectedItems.includes(item.id)"
                                :disabled="isBusy"
                                @change="toggleItem(item)"
                            />

                            <!-- Галочка — иконка Check, как у выполненного дела на карточке -->
                            <span class="checkbox-custom">
                                <Check
                                    v-if="selectedItems.includes(item.id)"
                                    :size="12"
                                />
                            </span>
                        </label>

                        <span
                            :class="[
                                'item-label',
                                { 'reserved-text': item.isSelected },
                            ]"
                        >
                            {{ item.label }}
                        </span>

                        <!-- Приоритет, цена и ссылка — узкой колонкой справа от названия,
                         каждое на своей строке: в одну строку они сильно сужали название -->
                        <div
                            v-if="
                                item.priority || item.price != null || item.url
                            "
                            class="item-meta"
                        >
                            <ItemPriorityHearts
                                v-if="item.priority"
                                :priority="item.priority"
                                :muted="item.isSelected"
                            />

                            <!-- Стоимость 0 ₽ тоже выводится: null — не указана -->
                            <span
                                v-if="item.price != null"
                                :class="[
                                    'item-price',
                                    { 'reserved-text': item.isSelected },
                                ]"
                            >
                                {{ formatPrice(item.price) }}
                            </span>

                            <a
                                v-if="item.url"
                                :href="item.url"
                                target="_blank"
                                rel="noopener noreferrer nofollow"
                                :class="[
                                    'item-link',
                                    { 'item-link--muted': item.isSelected },
                                ]"
                                :title="item.url"
                            >
                                <ExternalLink :size="12" />
                                <span class="item-link-host">
                                    {{ getItemUrlHost(item.url) }}
                                </span>
                            </a>
                        </div>

                        <button
                            v-if="item.isSelected && tokenForItem(item.id)"
                            class="cancel-btn"
                            type="button"
                            :disabled="isBusy"
                            :aria-label="`Отменить выбор: ${item.label}`"
                            :aria-busy="cancellingItemId === item.id"
                            @click="cancelItem(item)"
                        >
                            <span
                                :class="{
                                    'btn-text--hidden':
                                        cancellingItemId === item.id,
                                }"
                            >
                                Отменить
                            </span>
                            <LoaderButtonSpinner
                                v-if="cancellingItemId === item.id"
                                class="btn-spinner"
                                :size="12"
                            />
                        </button>
                    </div>

                    <!-- Правка совместного подарка на свою сохранённую позицию -->
                    <div
                        v-if="
                            item.isSelected &&
                            editingJointGiftItemId === item.id
                        "
                        class="joint-gift-form"
                    >
                        <JointGiftFields
                            v-model="jointGiftEditDraft"
                            :disabled="isBusy"
                        />

                        <div class="joint-gift-actions">
                            <button
                                type="button"
                                class="joint-gift-action joint-gift-action--primary"
                                :disabled="isBusy"
                                :aria-busy="jointGiftSaveAction === 'save'"
                                @click="saveJointGift(item)"
                            >
                                <!-- Текст скрыт, а не убран: ширина кнопки не меняется -->
                                <span
                                    :class="{
                                        'btn-text--hidden':
                                            jointGiftSaveAction === 'save',
                                    }"
                                >
                                    Сохранить
                                </span>
                                <LoaderButtonSpinner
                                    v-if="jointGiftSaveAction === 'save'"
                                    class="btn-spinner"
                                    :size="12"
                                />
                            </button>
                            <button
                                v-if="item.jointGift"
                                type="button"
                                class="joint-gift-action"
                                :disabled="isBusy"
                                :aria-busy="jointGiftSaveAction === 'remove'"
                                @click="saveJointGift(item, true)"
                            >
                                <span
                                    :class="{
                                        'btn-text--hidden':
                                            jointGiftSaveAction === 'remove',
                                    }"
                                >
                                    Не дарим вместе
                                </span>
                                <LoaderButtonSpinner
                                    v-if="jointGiftSaveAction === 'remove'"
                                    class="btn-spinner"
                                    :size="12"
                                />
                            </button>
                            <button
                                type="button"
                                class="joint-gift-action"
                                :disabled="isBusy"
                                @click="stopJointGiftEdit"
                            >
                                Отмена
                            </button>
                        </div>
                    </div>

                    <!-- Совместный подарок: данные организатора выводятся обычным текстом,
                     без ссылок, чтобы под видом чата нельзя было разместить чужой сайт -->
                    <div
                        v-else-if="item.isSelected && item.jointGift"
                        class="joint-gift-info"
                    >
                        <div class="joint-gift-info-text">
                            <p class="joint-gift-info-title">
                                <Users :size="12" />
                                <span>Дарим вместе</span>
                            </p>
                            <p class="joint-gift-info-organizer">
                                {{ jointGiftOrganizer(item.jointGift) }}
                            </p>
                            <p
                                v-if="item.jointGift.comment"
                                class="joint-gift-info-comment"
                            >
                                {{ item.jointGift.comment }}
                            </p>
                        </div>

                        <!-- Организатор может исправить свои данные; кнопка справа, по центру блока -->
                        <button
                            v-if="tokenForItem(item.id)"
                            class="cancel-btn"
                            type="button"
                            :disabled="isBusy"
                            :aria-label="`Изменить совместный подарок: ${item.label}`"
                            @click="startJointGiftEdit(item)"
                        >
                            Изменить
                        </button>
                    </div>

                    <!-- Своя сохранённая позиция без совместного подарка: его можно добавить -->
                    <div
                        v-else-if="item.isSelected && tokenForItem(item.id)"
                        class="joint-gift-form"
                    >
                        <button
                            type="button"
                            class="joint-gift-toggle"
                            :disabled="isBusy"
                            @click="startJointGiftEdit(item)"
                        >
                            <Users :size="12" />
                            Дарим вместе
                        </button>
                    </div>

                    <!-- Позиция отмечена, но ещё не сохранена: её можно предложить подарить вместе -->
                    <div
                        v-else-if="
                            !item.isSelected && selectedItems.includes(item.id)
                        "
                        class="joint-gift-form"
                    >
                        <button
                            type="button"
                            :class="[
                                'joint-gift-toggle',
                                {
                                    'joint-gift-toggle--active':
                                        jointGiftDrafts[item.id],
                                },
                            ]"
                            :aria-pressed="Boolean(jointGiftDrafts[item.id])"
                            :disabled="isBusy"
                            @click="toggleJointGift(item.id)"
                        >
                            <Users :size="12" />
                            Дарим вместе
                        </button>

                        <JointGiftFields
                            v-if="jointGiftDrafts[item.id]"
                            v-model="jointGiftDrafts[item.id]"
                            :disabled="isBusy"
                        />
                    </div>
                </template>

                <div class="card-actions">
                    <button
                        v-if="hasChanges"
                        class="card-actions-btn"
                        :disabled="isBusy"
                        :aria-label="
                            isSaving ? 'Сохранение…' : 'Сохранить выбор'
                        "
                        :aria-busy="isSaving"
                        @click="save"
                    >
                        <LoaderButtonSpinner v-if="isSaving" :size="18" />
                        <Save v-else :size="20" />
                    </button>
                </div>
            </div>
        </div>

        <div v-else class="empty-state">
            <p>Список не найден.</p>
        </div>
    </div>
</template>

<style scoped lang="scss">
@use '../../../scss/ui/checkboxCard.scss';

.wishlist-view {
    max-width: 800px;
    margin: 0 auto;
    padding: 24px;
}

.intro {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    margin-bottom: 20px;
    text-align: center;
}

.intro-title {
    margin: 0;
    font-size: 20px;
    font-weight: 700;
    line-height: 1.3;
    color: var(--ink, #241533);
    overflow-wrap: anywhere;
}

.intro-note {
    margin: 0;
    font-size: 12px;
    color: var(--ink-soft, #6b5878);
}

.loader,
.error,
.empty-state {
    text-align: center;
    color: #6b7280;
    font-size: 16px;
}

.error p {
    color: #dc2626;
}

.card {
    position: relative;
    display: flex;
    justify-content: center;
    flex-direction: column;
    background: linear-gradient(145deg, #fff 60%, #fff7fd);
    border: 1px solid rgba(226, 195, 211, 0.5);
    border-radius: 18px;
    padding: 18px;
    min-width: 300px;
}

.card-actions {
    position: absolute;
    display: flex;
    gap: 4px;
    top: 6px;
    right: 10px;
    font-size: 10px;
    color: #b3b3b3;

    .card-actions-btn {
        display: grid;
        place-items: center;
        width: 30px;
        height: 30px;
        border-radius: 8px;
        transition: all 0.18s ease;

        &:hover:not(:disabled) {
            color: #fff;
            background: var(--brand-gradient);
            cursor: pointer;
        }
    }
}

.card-header {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: space-between;
    gap: 6px;
    margin-top: 8px;
    margin-bottom: 14px;
}

.card-author {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: #b08cbe;
}

.card-author-avatar {
    display: grid;
    place-items: center;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: var(--brand-gradient);
    font-size: 10px;
    font-weight: 700;
    color: #fff;
    box-shadow: var(--shadow-glow);
}

.card-author-name {
    font-weight: 600;
    color: #8b5cf6;
}

.card-title {
    font-size: 14px;
    font-weight: 600;
    color: #3b2146;
}

// Переключатель порядка позиций: по порядку владельца, по приоритету или по стоимости.
// На узком экране четыре варианта переносятся на вторую строку
.item-order {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    align-self: center;
    gap: 2px;
    margin-bottom: 12px;
    padding: 3px;
    border-radius: 12px;
    background: rgba(139, 92, 246, 0.06);
}

.item-order-btn {
    padding: 4px 12px;
    border: none;
    border-radius: 9px;
    background: transparent;
    font-family: inherit;
    font-size: 12px;
    font-weight: 500;
    color: var(--ink-soft, #6b5878);
    cursor: pointer;
    transition: all 0.18s ease;

    &:hover:not(.item-order-btn--active) {
        color: var(--brand-violet);
    }
}

.item-order-btn--active {
    background: #fff;
    font-weight: 600;
    color: var(--brand-violet);
    box-shadow: 0 1px 4px rgba(139, 92, 246, 0.15);
}

.item {
    position: relative;
    // Свой контекст наложения: фон выбранной строки (::before, z-index: -1)
    // выводится под содержимым строки, но поверх фона карточки
    isolation: isolate;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 5px 0;
    border-bottom: 1px solid rgba(226, 195, 211, 0.25);
    cursor: pointer;
    user-select: none;
}

.item:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.item:first-of-type {
    padding-top: 0;
}

.item-label {
    flex: 1;
    min-width: 0;
    font-size: 13px;
    // Как в карточке на дашборде: чуть плотнее обычного текста.
    // На Windows 10 у Segoe UI нет начертания 500, там текст остаётся обычным
    font-weight: 500;
    color: #4a3356;
    transition: color 0.15s;
    line-height: 1.35;
    overflow-wrap: anywhere;
}

// Приоритет, цена и ссылка справа от названия, каждое на своей строке,
// прижаты к правому краю. Колонка не шире 40% строки: длинный домен
// в ссылке обрезается многоточием, а не сужает название
.item-meta {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 3px;
    flex-shrink: 0;
    max-width: 40%;

    > * {
        max-width: 100%;
    }
}

.item-price {
    flex-shrink: 0;
    font-size: 12px;
    font-weight: 600;
    color: var(--ink-soft, #6b5878);
    white-space: nowrap;
}

.item-link {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    flex-shrink: 0;
    padding: 3px 8px;
    border-radius: 8px;
    background: rgba(139, 92, 246, 0.08);
    font-size: 11px;
    font-weight: 600;
    color: var(--brand-violet);
    text-decoration: none;
    transition: all 0.18s ease;

    &:hover {
        color: #fff;
        background: var(--brand-gradient);
    }
}

/* Подарок уже выбран: ссылка остаётся (выбравшему гостю она нужна для покупки),
   но приглушена в тон серому тексту позиции, чтобы не зазывать купить повторно */
.item-link.item-link--muted {
    background: rgba(148, 163, 184, 0.12);
    font-weight: 500;
    color: #94a3b8;

    &:hover {
        color: #64748b;
        background: rgba(148, 163, 184, 0.22);
    }
}

.item-link-host {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.item.disabled {
    cursor: default;
}

/* Позиция, выбранная этим гостем (ещё не сохранена): тот же розовый фон,
   что у забронированных позиций на /dashboard.
   Фон задан псевдоэлементом, а не самой строкой: он не меняет размер строки
   (строка не прыгает при выборе). Отступ 1px сверху; снизу фон доходит до края
   строки, ещё 1px даёт прозрачная нижняя граница. Между соседними выбранными
   строками остаётся зазор 2px, как на /dashboard */
.item.item--mine {
    border-bottom-color: transparent;

    &::before {
        content: '';
        position: absolute;
        inset: 1px -8px 0;
        z-index: -1;
        border-radius: 10px;
        background: rgba(236, 72, 153, 0.06);
    }
}

/* Выбранная позиция: серые значок подарка и текст */
.reserved-icon {
    display: grid;
    place-items: center;
    flex-shrink: 0;
    width: 19px;
    height: 19px;
    border-radius: 7px;
    background: linear-gradient(135deg, #dbe2ea, #64748b);
    color: #fff;
}

.reserved-text {
    color: #94a3b8;
}

/* Отмеченный чекбокс: вместо символа «✓» из checkboxCard.scss — иконка Check,
   как у выполненного дела на карточке; фон остаётся фирменным градиентом */
.checkbox-custom {
    color: #fff;
}

.checkbox-input:checked + .checkbox-custom::after {
    content: none;
}

/* Чекбоксы недоступны только на время сохранения: отмеченные остаются в фирменных
   цветах, а не становятся серыми, как задано для disabled в checkboxCard.scss */
.checkbox-input:disabled + .checkbox-custom {
    cursor: default;
}

.checkbox-input:disabled:checked + .checkbox-custom {
    background: var(--brand-gradient);
}

.cancel-btn {
    flex-shrink: 0;
    padding: 3px 8px;
    border-radius: 8px;
    font-size: 11px;
    font-weight: 600;
    color: #db2777;
    cursor: pointer;
    transition: all 0.18s ease;

    &:hover:not(:disabled) {
        color: #fff;
        background: #ec4899;
    }

    &:disabled {
        cursor: default;
        opacity: 0.6;
    }
}

.reservation-notice {
    position: relative;
    display: flex;
    flex-direction: column;
    gap: 6px;
    margin-bottom: 14px;
    padding: 14px 16px;
    border: 1px solid rgba(236, 72, 153, 0.25);
    border-radius: 14px;
    background: rgba(236, 72, 153, 0.05);
}

.reservation-notice-close {
    position: absolute;
    top: 8px;
    right: 8px;
    display: grid;
    place-items: center;
    width: 24px;
    height: 24px;
    border-radius: 8px;
    color: #b08cbe;
    cursor: pointer;

    &:hover {
        color: var(--ink, #241533);
        background: rgba(139, 92, 246, 0.08);
    }
}

.reservation-notice-title {
    margin: 0;
    padding-right: 24px;
    font-size: 13px;
    font-weight: 600;
    color: var(--ink, #241533);
}

.reservation-notice-text {
    margin: 0;
    font-size: 12px;
    line-height: 1.45;
    color: var(--ink-soft, #6b5878);
}

.reservation-notice-link {
    display: flex;
    gap: 6px;
    margin-top: 2px;

    input {
        flex: 1;
        min-width: 0;
        padding: 6px 10px;
        border: 1px solid rgba(139, 92, 246, 0.2);
        border-radius: 10px;
        background: #fff;
        font-size: 12px;
        color: var(--ink-soft, #6b5878);
    }
}

.reservation-notice-copy {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    flex-shrink: 0;
    padding: 6px 12px;
    border-radius: 10px;
    background: var(--brand-gradient);
    font-size: 12px;
    font-weight: 600;
    color: #fff;
    cursor: pointer;
}

.reservation-notice-copy--error {
    background: #ef4444;
}

// Блок совместного подарка продолжает строку позиции: разделитель выводится под ним
.item:has(+ .joint-gift-info),
.item:has(+ .joint-gift-form) {
    border-bottom-color: transparent;
}

// Ширина карточки подстраивается под содержимое (страница центрирует её через flex).
// inline-size исключает эти блоки из расчёта: подсказка формы и длинный комментарий
// переносятся по ширине карточки, а не расширяют её
.joint-gift-info,
.joint-gift-form {
    contain: inline-size;
    display: flex;
    flex-direction: column;
    gap: 6px;
    // Блок и форма начинаются под названием подарка, а не под значком позиции
    padding: 4px 0 8px 29px;
    border-bottom: 1px solid rgba(226, 195, 211, 0.25);
}

// Три строки совместного подарка слева, кнопка «Изменить» справа по центру блока
.joint-gift-info {
    flex-direction: row;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}

// Строки идут вплотную; длинный текст переносится, а не выталкивает кнопку за край
.joint-gift-info-text {
    display: flex;
    flex-direction: column;
    gap: 0;
    min-width: 0;
}

// Плашка «Дарим вместе»: размеры как у кнопки .joint-gift-toggle, фон — фиолетовый
// градиент с тем же направлением и переходом, что у серого значка подарка (.reserved-icon).
// Рамка прозрачная, но сохраняет высоту плашки равной высоте кнопки
.joint-gift-info-title {
    display: inline-flex;
    align-items: center;
    align-self: flex-start;
    gap: 5px;
    margin: 0;
    padding: 3px 10px;
    border: 1px solid transparent;
    border-radius: 9px;
    background: linear-gradient(135deg, #c4b5fd, #8b5cf6);
    font-size: 11px;
    font-weight: 600;
    color: #fff;
    overflow-wrap: anywhere;

    svg {
        flex-shrink: 0;
    }
}

// Имя и контакт организатора — отдельной строкой под «Дарим вместе», над комментарием
.joint-gift-info-organizer {
    margin: 0;
    font-size: 12px;
    font-weight: 600;
    line-height: 1.4;
    color: var(--ink, #241533);
    overflow-wrap: anywhere;
}

.joint-gift-info-comment {
    margin: 0;
    font-size: 12px;
    line-height: 1.4;
    color: var(--ink-soft, #6b5878);
    overflow-wrap: anywhere;
}

.joint-gift-toggle {
    display: inline-flex;
    align-items: center;
    align-self: flex-start;
    gap: 5px;
    padding: 3px 10px;
    border: 1px solid rgba(139, 92, 246, 0.25);
    border-radius: 9px;
    font-size: 11px;
    font-weight: 600;
    color: var(--brand-violet);
    cursor: pointer;
    transition: all 0.18s ease;

    &:hover:not(:disabled) {
        background: rgba(139, 92, 246, 0.08);
    }

    &:disabled {
        cursor: default;
        opacity: 0.6;
    }
}

.joint-gift-toggle--active {
    border-color: transparent;
    background: var(--brand-gradient);
    color: #fff;

    &:hover:not(:disabled) {
        background: var(--brand-gradient);
    }
}

.joint-gift-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.joint-gift-action {
    padding: 4px 10px;
    border: 1px solid rgba(139, 92, 246, 0.25);
    border-radius: 9px;
    font-size: 11px;
    font-weight: 600;
    color: var(--brand-violet);
    cursor: pointer;
    transition: all 0.18s ease;

    &:hover:not(:disabled) {
        background: rgba(139, 92, 246, 0.08);
    }

    &:disabled {
        cursor: default;
        opacity: 0.6;
    }
}

// Спиннер на кнопке выводится поверх скрытого текста, по центру:
// текст продолжает занимать место, поэтому ширина кнопки не меняется
.joint-gift-action,
.cancel-btn {
    position: relative;
}

.btn-text--hidden {
    visibility: hidden;
}

.btn-spinner {
    position: absolute;
    inset: 0;
    margin: auto;
}

.joint-gift-action--primary {
    border-color: transparent;
    background: var(--brand-gradient);
    color: #fff;

    &:hover:not(:disabled) {
        background: var(--brand-gradient);
    }
}

.cancel-error {
    margin: 0 0 10px;
    font-size: 12px;
    text-align: center;
    color: #dc2626;
}
</style>
