<script setup lang="ts">
import {
    Check,
    Copy,
    ExternalLink,
    Gift,
    RefreshCw,
    Users,
    X,
} from '@lucide/vue';
import { isAxiosError } from 'axios';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import { useGuestReservations } from '@/composables/useGuestReservations';
import { useGuestTodoChecks } from '@/composables/useGuestTodoChecks';
import { api } from '@/lib/api';
import { copyToClipboard } from '@/lib/clipboard';
import { formatPrice } from '@/lib/itemPrice';
import { getItemUrlShortHost } from '@/lib/itemUrl';
import { getSharedWishlistPath, getSharedWishlistUrl } from '@/lib/sharedLink';
import type {
    JointGiftDraft,
    Wishlist,
    WishlistItem,
    WishlistJointGift,
} from '@/types/wishlist';

import LaoderPageSpinner from '../ui/LaoderPageSpinner.vue';
import LoaderButtonSpinner from '../ui/LoaderButtonSpinner.vue';
import UserAvatar from '../ui/UserAvatar.vue';
import ItemPriorityHearts from './ItemPriorityHearts.vue';
import JointGiftFields from './JointGiftFields.vue';
import TodoCheckedBy from './TodoCheckedBy.vue';
import TodoGuestNameModal from './TodoGuestNameModal.vue';

const route = useRoute();
const router = useRouter();
const wishlist = ref<Wishlist | null>(null);
const isLoading = ref(true);
const error = ref<string | null>(null);
const wishlistId = String(route.params.id || '');
const selectedItems = ref<string[]>([]);
const isSaving = ref(false);

// Список дел, открытый владельцем по ссылке: гость видит, какие дела выполнены,
// а отмечает их, только если владелец разрешил. Броней у такого списка нет
const isTodo = computed(() => wishlist.value?.type === 'todo');

// Гость может отметить невыполненные дела; снять отметку может только владелец
const canCheckTodo = computed(
    () => isTodo.value && Boolean(wishlist.value?.guestsCanCheck),
);

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
// Идёт обновление списка кнопкой «Обновить»
const isRefreshing = ref(false);

const isBusy = computed(
    () =>
        isSaving.value ||
        isRefreshing.value ||
        cancellingItemId.value !== null ||
        savingJointGiftItemId.value !== null,
);

// Токен брони из ссылки для отмены (?reservation=…)
const tokenFromLink = (): string | null => {
    const value = route.query.reservation;

    return typeof value === 'string' ? value : null;
};

// Брони бывают только у списка желаний
const buildReservationLink = (token: string): string =>
    `${getSharedWishlistUrl(wishlistId, 'gift')}?reservation=${token}`;

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

        // У списка дел броней нет: сервер отвечает на их запрос 404
        if (!isTodo.value) {
            await loadReservations(linkToken);
        }

        // Адрес приводится к типу списка: список дел, открытый по адресу
        // списка желаний (в том числе по прежней ссылке), и наоборот
        const path = getSharedWishlistPath(wishlistId, wishlist.value.type);

        if (linkToken || route.path !== path) {
            void router.replace({
                path,
                query: linkToken ? {} : route.query,
                hash: route.hash,
            });
        }
    } catch (fetchError) {
        console.error('Ошибка загрузки списка:', fetchError);

        // На 404 сервер одинаково отвечает для удалённого списка, неверной ссылки
        // и списка дел, доступ к которому владелец выключил: причину гостю не раскрывают
        error.value =
            isAxiosError(fetchError) && fetchError.response?.status === 404
                ? 'Список не найден. Возможно, ссылка неверна, список удалён или владелец закрыл доступ по ссылке.'
                : 'Не удалось загрузить список. Проверьте URL или попробуйте позже.';
        wishlist.value = null;
    } finally {
        isLoading.value = false;
        isSaving.value = false;
    }
};

// Свежие данные списка без перезагрузки страницы: список остаётся на экране,
// а отмеченные, но ещё не сохранённые позиции остаются отмеченными, если их
// по-прежнему можно выбрать. Позиции, которые за это время выбрал кто-то другой
// или удалил владелец, из несохранённого выбора убираются
const refreshWishlist = async (): Promise<void> => {
    if (isBusy.value) return;

    isRefreshing.value = true;
    actionError.value = null;

    try {
        const response = await api.get<{ data: Wishlist }>(
            `/api/v1/shared-wishlists/${wishlistId}`,
        );

        wishlist.value = response.data.data;

        // Владелец мог снять выбор гостя: брони тоже загружаются заново
        if (!isTodo.value) {
            await loadReservations();
        }

        const selectableIds = new Set(
            wishlist.value.items
                .filter((item) => !item.isSelected)
                .map((item) => item.id),
        );

        selectedItems.value = selectedItems.value.filter((id) =>
            selectableIds.has(id),
        );

        for (const itemId of Object.keys(jointGiftDrafts.value)) {
            if (!selectableIds.has(itemId)) {
                delete jointGiftDrafts.value[itemId];
            }
        }

        // Позиция, совместный подарок которой правит гость, больше не его
        if (
            editingJointGiftItemId.value &&
            !tokenForItem(editingJointGiftItemId.value)
        ) {
            stopJointGiftEdit();
        }
    } catch (refreshError) {
        console.error('Ошибка обновления списка:', refreshError);

        // Список удалили или закрыли, пока страница была открыта: показывается
        // то же сообщение, что и при первой загрузке
        if (
            isAxiosError(refreshError) &&
            refreshError.response?.status === 404
        ) {
            error.value =
                'Список не найден. Возможно, ссылка неверна, список удалён или владелец закрыл доступ по ссылке.';
            wishlist.value = null;
            return;
        }

        actionError.value =
            'Не удалось обновить список. Проверьте подключение и попробуйте снова.';
    } finally {
        isRefreshing.value = false;
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

// Имя гостя для подписи под отмеченными делами. Хранится в браузере и одно
// для всех списков: гость вводит его один раз
const GUEST_NAME_STORAGE_KEY = 'todo-guest-name';

// Доступ к localStorage в приватном режиме может выбросить исключение
const readGuestName = (): string => {
    try {
        return window.localStorage.getItem(GUEST_NAME_STORAGE_KEY) ?? '';
    } catch {
        return '';
    }
};

const writeGuestName = (name: string): void => {
    try {
        if (name) {
            window.localStorage.setItem(GUEST_NAME_STORAGE_KEY, name);
        } else {
            window.localStorage.removeItem(GUEST_NAME_STORAGE_KEY);
        }
    } catch {
        // Сохранение недоступно: имя придётся ввести на следующей странице заново
    }
};

const guestName = ref(readGuestName());

// Гость отказался указывать имя, когда оно необязательно; до перезагрузки
// страницы окно больше не открывается
const isNameSkipped = ref(false);

// Окно ввода имени: check — перед отметкой дел, edit — гость меняет имя
const nameModalMode = ref<'check' | 'edit' | null>(null);

const isGuestNameRequired = computed(() =>
    Boolean(wishlist.value?.guestNameRequired),
);

// Дела, которые гость отметил из этого браузера, подписываются «Вы»
const guestTodoChecks = useGuestTodoChecks(wishlistId);

// Свежие данные списка: забываются дела, с которых владелец снял отметку
watch(wishlist, (value) => {
    if (value?.type === 'todo') guestTodoChecks.prune(value.items);
});

// Отметить выбранные дела выполненными. Уже выполненные дела сервер не меняет,
// поэтому гость, отметивший дело одновременно с другим, ошибки не увидит
const checkTodoItems = async (): Promise<void> => {
    if (selectedItems.value.length === 0 || isBusy.value) return;

    isSaving.value = true;
    actionError.value = null;

    try {
        const response = await api.post<{ data: Wishlist }>(
            `/api/v1/shared-wishlists/${wishlistId}/items/check`,
            { item_ids: selectedItems.value, name: guestName.value || null },
        );

        guestTodoChecks.remember(
            response.data.data.items,
            selectedItems.value,
            guestName.value || null,
        );
        wishlist.value = response.data.data;
        selectedItems.value = [];
    } catch (checkError) {
        console.error('Ошибка отметки дел:', checkError);

        const status = isAxiosError(checkError)
            ? checkError.response?.status
            : undefined;

        // 422 без имени: владелец сделал имя обязательным, пока страница была открыта
        if (status === 422 && !guestName.value && wishlist.value) {
            wishlist.value.guestNameRequired = true;
            nameModalMode.value = 'check';
            return;
        }

        // 404: владелец запретил отмечать дела или закрыл доступ, пока страница была открыта
        actionError.value =
            status === 404
                ? 'Не удалось отметить дела: владелец запретил отмечать их по ссылке. Обновите страницу.'
                : 'Не удалось отметить дела. Обновите страницу и попробуйте снова.';
    } finally {
        isSaving.value = false;
    }
};

// Перед первой отметкой гость указывает имя; если оно уже есть или гость
// отказался от необязательного имени, дела отмечаются сразу
const requestTodoCheck = (): void => {
    if (
        guestName.value ||
        (!isGuestNameRequired.value && isNameSkipped.value)
    ) {
        void checkTodoItems();
        return;
    }

    nameModalMode.value = 'check';
};

const confirmGuestName = (name: string): void => {
    const mode = nameModalMode.value;

    guestName.value = name;
    writeGuestName(name);
    nameModalMode.value = null;

    if (mode === 'check') void checkTodoItems();
};

const skipGuestName = (): void => {
    isNameSkipped.value = true;
    nameModalMode.value = null;
    void checkTodoItems();
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

// Все подарки уже выбраны гостями (выбирать больше нечего) или все дела выполнены
const allSelected = computed(() => {
    const items = wishlist.value?.items ?? [];

    return items.length > 0 && items.every((item) => item.isSelected);
});

// «Выполнено 2 из 5» под заголовком списка дел
const todoProgressLabel = computed(() => {
    const items = wishlist.value?.items ?? [];
    const done = items.filter((item) => item.isSelected).length;

    return `Выполнено ${done} из ${items.length}`;
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
                <!-- Шапка автора: кружок с первой буквой слева, справа имя и подпись
                     отдельными строками. Для экранного диктора заголовок читается
                     одной фразой: «Аня делится с вами списком подарков» -->
                <div class="intro-author">
                    <UserAvatar
                        v-if="wishlist.username"
                        class="intro-author-avatar"
                        :username="wishlist.username"
                        :avatar-url="wishlist.avatarUrl"
                    />

                    <h1 id="shared-intro-title" class="intro-title">
                        <template v-if="wishlist.username">
                            <span class="intro-author-name">
                                {{ wishlist.username }}
                            </span>
                            <span class="intro-subtitle">
                                делится с вами
                                {{
                                    isTodo ? 'списком дел' : 'списком подарков'
                                }}
                            </span>
                        </template>
                        <template v-else>
                            С вами поделились
                            {{ isTodo ? 'списком дел' : 'списком подарков' }}
                        </template>
                    </h1>
                </div>

                <p v-if="isTodo" class="intro-note">
                    {{ allSelected ? 'Все дела выполнены' : todoProgressLabel }}
                </p>

                <p v-else-if="allSelected" class="intro-note">
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
                <!-- Свежие данные без перезагрузки страницы: например, чтобы увидеть,
                     что выбрали или отметили другие гости -->
                <button
                    type="button"
                    :class="[
                        'refresh-btn',
                        { 'refresh-btn--spinning': isRefreshing },
                    ]"
                    :disabled="isBusy"
                    :aria-busy="isRefreshing"
                    aria-label="Обновить список"
                    title="Обновить список"
                    @click="refreshWishlist"
                >
                    <RefreshCw :size="14" />
                </button>

                <div class="card-header">
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
                                disabled:
                                    item.isSelected ||
                                    (isTodo && !canCheckTodo),
                                'item--todo': isTodo,
                                'item--done': isTodo && item.isSelected,
                                'item--mine':
                                    selectedItems.includes(item.id) ||
                                    (item.isSelected && tokenForItem(item.id)),
                            },
                        ]"
                        :title="
                            isTodo
                                ? undefined
                                : item.isSelected
                                  ? tokenForItem(item.id)
                                      ? 'Ваш выбор'
                                      : 'Уже выбрано'
                                  : undefined
                        "
                    >
                        <!-- Дело, которое гость не может отметить: выполненное — белая галочка
                             на зелёном фоне, как на карточке владельца, невыполненное в списке
                             только для просмотра — пустой квадрат. Остальные дела — чекбоксом ниже -->
                        <span
                            v-if="isTodo && (item.isSelected || !canCheckTodo)"
                            :class="[
                                'todo-status',
                                { 'todo-status--done': item.isSelected },
                            ]"
                            role="img"
                            :aria-label="
                                item.isSelected ? 'Выполнено' : 'Не выполнено'
                            "
                        >
                            <Check v-if="item.isSelected" :size="12" />
                        </span>

                        <!-- Позицию выбрал этот гость: серый значок подарка, выбор можно отменить кнопкой справа -->
                        <span
                            v-else-if="item.isSelected && tokenForItem(item.id)"
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

                        <!-- Справа от выполненного дела — кто его отметил -->
                        <span class="item-text">
                            <span
                                :class="[
                                    'item-label',
                                    { 'reserved-text': item.isSelected },
                                ]"
                            >
                                {{ item.label }}
                            </span>
                            <!-- Отметки владельца подписываются его именем,
                                 отметки из этого браузера — словом «Вы» -->
                            <TodoCheckedBy
                                v-if="isTodo"
                                :checked-by="item.checkedBy"
                                :owner-name="wishlist.username ?? 'Владелец'"
                                :is-own="guestTodoChecks.isOwnCheck(item)"
                            />
                        </span>

                        <!-- Приоритет, цена и ссылки — узкой колонкой справа от названия,
                         каждое на своей строке (ссылки — общей строкой): в одну строку
                         они сильно сужали название -->
                        <div
                            v-if="
                                item.priority ||
                                item.price != null ||
                                item.urls?.length
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

                            <!-- Все ссылки позиции в одну строку -->
                            <div v-if="item.urls?.length" class="item-links">
                                <a
                                    v-for="(url, urlIndex) in item.urls"
                                    :key="urlIndex"
                                    :href="url"
                                    target="_blank"
                                    rel="noopener noreferrer nofollow"
                                    :class="[
                                        'item-link',
                                        { 'item-link--muted': item.isSelected },
                                    ]"
                                    :title="url"
                                >
                                    <ExternalLink :size="12" />
                                    <span class="item-link-host">
                                        {{ getItemUrlShortHost(url) }}
                                    </span>
                                </a>
                            </div>
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
                            !isTodo &&
                            !item.isSelected &&
                            selectedItems.includes(item.id)
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

                <!-- Как «Сохранить» в модальных окнах: неактивна, пока не отмечен ни один подарок.
                     Если все подарки уже выбраны, бронировать нечего и кнопки нет.
                     В списке дел кнопка есть, только если владелец разрешил гостям отмечать дела -->
                <button
                    v-if="(!isTodo || canCheckTodo) && !allSelected"
                    type="button"
                    class="create-btn reserve-btn"
                    :disabled="isBusy || !hasChanges"
                    :aria-busy="isSaving"
                    @click="isTodo ? requestTodoCheck() : save()"
                >
                    <LoaderButtonSpinner v-if="isSaving" :size="18" />

                    <span v-else>
                        {{ isTodo ? 'Отметить выполненными' : 'Я подарю' }}
                    </span>
                </button>

                <!-- Под кнопкой: от чьего имени гость отмечает дела -->
                <p
                    v-if="canCheckTodo && !allSelected && guestName"
                    class="guest-name-line"
                >
                    Вы отмечаете как
                    <b>{{ guestName }}</b>
                    ·
                    <button
                        type="button"
                        class="guest-name-change"
                        :disabled="isBusy"
                        @click="nameModalMode = 'edit'"
                    >
                        изменить
                    </button>
                </p>
            </div>
        </div>

        <div v-else class="empty-state">
            <p>Список не найден.</p>
        </div>

        <TodoGuestNameModal
            v-if="nameModalMode"
            :required="isGuestNameRequired"
            :initial-name="guestName"
            :mode="nameModalMode"
            @close="nameModalMode = null"
            @confirm="confirmGuestName"
            @skip="skipGuestName"
        />
    </div>
</template>

<style scoped lang="scss">
@use '../../../scss/ui/checkboxCard.scss';
@use '../../../scss/ui/createButton.scss';

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
    color: var(--app-ink, var(--ink, #241533));
    overflow-wrap: anywhere;
}

// Кружок слева, имя и подпись справа; вся шапка — по центру страницы
.intro-author {
    display: flex;
    align-items: center;
    gap: 12px;
    max-width: 100%;
}

// Аватар автора, как в профиле
.intro-author-avatar {
    width: 52px;
    height: 52px;
    border: 3px solid #fff;
    background: var(--brand-gradient);
    font-size: 22px;
    font-weight: 700;
    color: #fff;
    box-shadow: var(--shadow-glow);
}

// Имя и подпись — отдельными строками: имя крупно и цветом, подпись мельче и спокойнее
// Рядом с кружком текст выровнен по левому краю; длинное имя переносится, не сдвигая кружок
.intro-title:has(.intro-author-name) {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
    text-align: left;
}

.intro-author-name {
    font-size: 24px;
    line-height: 1.2;
    color: var(--app-accent, #8b5cf6);
}

.intro-subtitle {
    font-size: 15px;
    font-weight: 500;
    color: var(--app-ink-soft, var(--ink-soft, #6b5878));
}

.intro-note {
    margin: 0;
    font-size: 12px;
    color: var(--app-ink-soft, var(--ink-soft, #6b5878));
}

.loader,
.error,
.empty-state {
    text-align: center;
    color: var(--app-ink-soft, #6b7280);
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

// Кнопка «Обновить» в правом верхнем углу карточки, как кнопки действий
// на карточке в дашборде. Во время обновления значок вращается
.refresh-btn {
    position: absolute;
    top: 12px;
    right: 12px;
    display: grid;
    place-items: center;
    width: 28px;
    height: 28px;
    border: none;
    border-radius: 9px;
    background: rgba(139, 92, 246, 0.08);
    color: var(--brand-violet);
    cursor: pointer;
    transition: all 0.18s ease;

    &:hover:not(:disabled) {
        color: #fff;
        background: var(--brand-gradient);
    }

    &:disabled {
        cursor: default;
        opacity: 0.6;
    }
}

.refresh-btn--spinning svg {
    animation: refresh-spin 0.8s linear infinite;
}

@keyframes refresh-spin {
    to {
        transform: rotate(360deg);
    }
}

// Отступ от списка подарков; остальные стили — в createButton.scss
.reserve-btn {
    margin-top: 16px;
}

.card-header {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: space-between;
    gap: 6px;
    margin-top: 8px;
    margin-bottom: 14px;
    // Длинное название не заходит под кнопку «Обновить»
    padding: 0 32px;
}

.card-title {
    font-size: 16px;
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

// Название слева, имя того, кто отметил дело (TodoCheckedBy), справа в той же строке
.item-text {
    display: flex;
    flex: 1;
    align-items: baseline;
    gap: 8px;
    min-width: 0;

    .item-label {
        flex: 1;
    }
}

.guest-name-line {
    margin: 10px 0 0;
    font-size: 12px;
    text-align: center;
    color: var(--ink-soft, #6b5878);
    overflow-wrap: anywhere;

    b {
        font-weight: 600;
        color: var(--ink, #241533);
    }
}

.guest-name-change {
    padding: 0;
    border: none;
    background: none;
    font-family: inherit;
    font-size: inherit;
    font-weight: 600;
    color: var(--brand-violet);
    cursor: pointer;

    &:hover:not(:disabled) {
        text-decoration: underline;
    }

    &:disabled {
        cursor: default;
        opacity: 0.6;
    }
}

.item-label {
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

// Ссылки позиции в одну строку. Домен уже укорочен до 7 символов
// (getItemUrlShortHost); если ссылки всё равно не помещаются в колонку,
// они сужаются и домен дополнительно обрезается многоточием
.item-links {
    display: flex;
    justify-content: flex-end;
    gap: 4px;
    min-width: 0;
}

.item-link {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    min-width: 0;
    padding: 3px 8px;

    // Иконка не сжимается, когда ссылке не хватает места
    svg {
        flex-shrink: 0;
    }
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

/* Дело в списке только для просмотра: значок того же размера, что чекбокс
   и значок подарка, в зелёных тонах списка дел. Выполненное дело зачёркнуто */
.todo-status {
    display: grid;
    place-items: center;
    flex-shrink: 0;
    width: 19px;
    height: 19px;
    border: 1.5px solid rgba(16, 185, 129, 0.45);
    border-radius: 7px;
    color: #fff;
}

.todo-status--done {
    border-color: transparent;
    background: linear-gradient(135deg, #6ee7b7, #10b981);
}

/* Дело, отмеченное гостем, но ещё не сохранённое: зелёный фон строки и чекбокса
   вместо розового у подарков, в тон выполненным делам */
.item.item--todo.item--mine::before {
    background: rgba(16, 185, 129, 0.08);
}

.item--todo .checkbox-custom {
    border-color: rgba(16, 185, 129, 0.45);

    &:hover {
        border-color: #10b981;
    }
}

.item--todo .checkbox-input:checked + .checkbox-custom,
.item--todo .checkbox-input:disabled:checked + .checkbox-custom {
    background: linear-gradient(135deg, #6ee7b7, #10b981);
}

.item.item--done .item-label {
    color: #94a3b8;
    text-decoration: line-through;
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
