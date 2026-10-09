<script setup lang="ts">
import {
    Check,
    ChevronDown,
    ChevronUp,
    Ellipsis,
    Gift,
    Link,
    Plus,
    Trash2,
    X,
} from '@lucide/vue';
import { isAxiosError } from 'axios';
import {
    computed,
    onMounted,
    onUnmounted,
    ref,
    useTemplateRef,
    watch,
} from 'vue';

import { api } from '@/lib/api';

import { useItemReorder } from '../../composables/useItemReorder';
import { NOTE_CONTENT_MAX_LENGTH } from '../../constants/note';
import {
    getItemUrlErrors,
    MAX_ITEM_URLS,
    normalizeItemUrls,
} from '../../lib/itemUrl';
import { nudgeUnsaved } from '../../lib/nudgeUnsaved';
import { checkedByName } from '../../lib/todoCheckedBy';
import type {
    Wishlist,
    WishlistForm,
    WishlistItem,
    WishlistItemCheckedBy,
    WishlistUpdatePayload,
} from '../../types/wishlist';
import LoaderButtonSpinner from '../ui/LoaderButtonSpinner.vue';
import ItemPriceInput from './ItemPriceInput.vue';
import ItemPriorityPicker from './ItemPriorityPicker.vue';
import TodoCheckedBy from './TodoCheckedBy.vue';
import WishlistColorPicker from './WishlistColorPicker.vue';
import WishlistGuestCheckToggle from './WishlistGuestCheckToggle.vue';
import WishlistGuestNameToggle from './WishlistGuestNameToggle.vue';
import WishlistShareToggle from './WishlistShareToggle.vue';
import WishlistSurpriseToggle from './WishlistSurpriseToggle.vue';

const props = defineProps<{
    wishlist: Wishlist;
    isPending: boolean;
    // Текст ошибки сервера после неудачной попытки сохранить список
    errorMessage?: string | null;
}>();

const emit = defineEmits<{
    close: [];
    update: [payload: WishlistUpdatePayload];
    // Владелец снял выбор гостя: список с сервера, чтобы обновить карточку
    selectionCleared: [wishlist: Wishlist];
}>();

// Тип списка при редактировании не меняется, поэтому в форме его нет
type EditForm = Omit<WishlistForm, 'type'>;

const defaultForm = (): EditForm => ({
    title: '',
    content: '',
    color: 'white',
    hideSelections: false,
    isShared: false,
    guestsCanCheck: false,
    guestNameRequired: true,
    items: [
        {
            isSelected: false,
            label: '',
        },
    ],
});

const form = ref<EditForm>({
    title: '',
    content: '',
    color: 'white',
    hideSelections: false,
    isShared: false,
    guestsCanCheck: false,
    guestNameRequired: true,
    items: [],
});

// Ошибки полей ссылок по индексам позиций; ошибка снимается, когда ссылку начинают править
const urlErrors = ref<boolean[][]>([]);

// Выбор гостей, полученный после выключения режима сюрприза в этом окне
const isSelectionRevealed = ref(false);
const isSelectionLoading = ref(false);
const selectionError = ref<string | null>(null);

// Серверное время, на которое окно получило выбор гостей. Передаётся при снятии
// выбора: сервер не снимет выбор, сделанный позже, — его владелец не видел
const checkedAt = ref<string | null>(null);

// Номер последнего запроса выбора: ответ на более ранний запрос не применяется
let selectionRequestId = 0;

// Снятие выбора гостя: позиция, по которой идёт запрос, позиция, для которой
// показано подтверждение, и сообщения под позициями
const clearingItemId = ref<string | null>(null);
const confirmingItemId = ref<string | null>(null);
const itemMessages = ref<Record<string, { text: string; isError: boolean }>>(
    {},
);

// Форма в том виде, в котором её сохранит сервер: пустые позиции отброшены,
// ссылки нормализованы, пробелы по краям текста не учитываются.
// При ignoreSelection отметки не сравниваются: в списке желаний сервер
// не принимает их из формы, а владелец снимает выбор гостя отдельным запросом
const formSnapshot = (value: EditForm, ignoreSelection: boolean): string =>
    JSON.stringify({
        title: value.title.trim(),
        content: value.content.trim(),
        color: value.color,
        hideSelections: value.hideSelections,
        isShared: value.isShared,
        guestsCanCheck: value.guestsCanCheck,
        guestNameRequired: value.guestNameRequired,
        items: value.items
            .filter((item) => item.label.trim())
            .map((item) => ({
                id: item.id ?? null,
                label: item.label.trim(),
                urls: normalizeItemUrls(item.urls),
                priority: item.priority ?? null,
                price: item.price ?? null,
                isSelected: ignoreSelection ? null : Boolean(item.isSelected),
            })),
    });

// Снимок формы при открытии окна: с ним сравнивается текущая форма
const initialSnapshot = ref('');

watch(
    () => props.wishlist,
    (wishlist) => {
        // Форма заполняется заново, с режимом сюрприза из сохранённого списка
        isSelectionRevealed.value = false;
        selectionError.value = null;
        checkedAt.value = null;
        confirmingItemId.value = null;
        itemMessages.value = {};
        urlErrors.value = [];

        form.value = {
            title: wishlist.title ?? '',
            content: wishlist.content ?? '',
            color: wishlist.color,
            hideSelections: wishlist.hideSelections ?? false,
            isShared: wishlist.isShared ?? false,
            guestsCanCheck: wishlist.guestsCanCheck ?? false,
            guestNameRequired: wishlist.guestNameRequired ?? true,
            // id нужен серверу, чтобы в режиме сюрприза сохранить выбор гостей
            items: wishlist.items.map((item) => ({
                id: item.id,
                label: item.label,
                urls: item.urls?.length ? [...item.urls] : [''],
                priority: item.priority ?? null,
                price: item.price ?? null,
                isSelected: item.isSelected ?? false,
            })),
        };

        initialSnapshot.value = formSnapshot(
            form.value,
            wishlist.type !== 'todo',
        );
    },
    {
        immediate: true,
    },
);

// Кнопка «Сохранить» активна, только если форма отличается от сохранённого списка.
// Если вернуть всё как было, кнопка снова станет неактивной
const isDirty = computed(
    () =>
        formSnapshot(form.value, props.wishlist.type !== 'todo') !==
        initialSnapshot.value,
);

// Список дел: у позиций только текст и отметка «выполнено»
const isTodo = computed(() => props.wishlist.type === 'todo');

// Заметка: изменяются только цвет и текст
const isNote = computed(() => props.wishlist.type === 'note');

// Кто отметил дело, с учётом изменений в форме: дело, выполненное и до открытия
// окна, сохраняет прежнего автора отметки, а отметку, поставленную в окне,
// ставит владелец. Так сервер и сохранит её (WishlistService::syncItems)
const formCheckedBy = (item: WishlistItem): WishlistItemCheckedBy | null => {
    if (!item.isSelected) return null;

    const saved = props.wishlist.items.find((saved) => saved.id === item.id);

    return saved?.isSelected
        ? (saved.checkedBy ?? null)
        : { guest: false, name: null };
};

// Свои отметки подписываются «Вы», как на карточке: если гости могут отмечать
// дела или в списке есть их отметки
const ownCheckLabel = computed(() => {
    const guestsCanCheck = form.value.isShared && form.value.guestsCanCheck;
    const hasGuestChecks = props.wishlist.items.some(
        (item) => item.checkedBy?.guest,
    );

    return guestsCanCheck || hasGuestChecks ? 'Вы' : null;
});

// Есть ли у дела имя того, кто его отметил
const hasCheckedByCaption = (item: WishlistItem): boolean =>
    checkedByName(formCheckedBy(item), ownCheckLabel.value) !== null;

// Если хотя бы у одного дела над полем есть имя, подпись выводится у всех дел:
// у дел без имени — «Не выполнено», и поля всех дел стоят на одном уровне
const showCheckedByRow = computed(
    () => isTodo.value && form.value.items.some(hasCheckedByCaption),
);

// Пустую заметку сервер не принимает, поэтому кнопка сохранения недоступна
const isNoteEmpty = computed(() => isNote.value && !form.value.content.trim());

// Название списка не заполнено: ошибка выводится под полем после попытки отправки
// и снимается, как только название начинают вводить
const isTitleMissing = ref(false);

// Режим сюрприза был включён при открытии окна: сервер не прислал isSelected,
// а при сохранении не примет его из формы
const wasSelectionHidden = computed(() =>
    Boolean(props.wishlist.hideSelections),
);

// Выбор гостей и время, на которое он получен. Окно запрашивает его при открытии,
// чтобы отметки были актуальны не на время загрузки дашборда, а на время открытия.
// В режиме сюрприза id позиций приходят только с reveal: владелец выключил режим в окне.
// Отметки и checkedAt обновляются вместе, поэтому окно показывает выбор ровно на checkedAt
const loadSelections = async (reveal: boolean): Promise<void> => {
    const requestId = ++selectionRequestId;

    isSelectionLoading.value = true;
    selectionError.value = null;

    try {
        const response = await api.get<{
            data: { checkedAt: string; itemIds?: string[] };
        }>(`/v1/wishlists/${props.wishlist.id}/selections`, {
            params: reveal ? { reveal: 1 } : undefined,
        });

        if (requestId !== selectionRequestId) return;

        const { checkedAt: time, itemIds } = response.data.data;

        checkedAt.value = time;

        if (itemIds) {
            const selectedIds = new Set(itemIds);

            // Позиции, добавленные в этом окне, ещё не сохранены и выбраны быть не могут
            form.value.items.forEach((item) => {
                item.isSelected = Boolean(item.id && selectedIds.has(item.id));
            });
        }

        if (reveal) isSelectionRevealed.value = true;
    } catch (error) {
        if (requestId !== selectionRequestId) return;

        console.error('Ошибка загрузки выбора гостей:', error);
        selectionError.value = reveal
            ? 'Не удалось загрузить выбор гостей. Он будет виден после сохранения списка.'
            : 'Не удалось загрузить выбор гостей. Закройте окно и откройте снова.';
    } finally {
        if (requestId === selectionRequestId) isSelectionLoading.value = false;
    }
};

// Выбор запрашивается с reveal, если владелец видит его в окне, хотя список
// сохранён в режиме сюрприза
const refreshSelections = (): Promise<void> =>
    loadSelections(wasSelectionHidden.value && !form.value.hideSelections);

// Список сохранён, окно осталось открытым: выбор гостей и checkedAt
// запрашиваются заново, как при открытии окна
watch(
    () => props.wishlist,
    () => {
        if (!isTodo.value && !isNote.value) void loadSelections(false);
    },
);

// Владелец выключил режим сюрприза: выбор гостей запрашивается сразу,
// чтобы показать его до сохранения списка
watch(
    () => form.value.hideSelections,
    (hideSelections) => {
        if (
            !hideSelections &&
            wasSelectionHidden.value &&
            !isSelectionRevealed.value
        ) {
            void loadSelections(true);
        }
    },
);

// Чекбоксы выбора показываются, только если владелец видит выбор гостей
const showSelection = computed(
    () =>
        !form.value.hideSelections &&
        (!wasSelectionHidden.value || isSelectionRevealed.value),
);

const setItemMessage = (
    itemId: string,
    text: string | null,
    isError = false,
): void => {
    const messages = { ...itemMessages.value };

    if (text) {
        messages[itemId] = { text, isError };
    } else {
        delete messages[itemId];
    }

    itemMessages.value = messages;
};

// Снять выбор гостя: отдельный запрос, сразу, без сохранения формы, поэтому
// несохранённые правки не теряются (окно получает копию списка, и её сервер
// не меняет). Если владелец видит выбор, кнопка есть только у выбранных позиций.
// В режиме сюрприза владелец освобождает позицию вслепую: ответ сервера не
// раскрывает, была ли она выбрана, поэтому и сообщение нейтральное
const clearSelection = async (item: WishlistItem): Promise<void> => {
    if (!item.id) return;

    const itemId = item.id;
    const isBlind = form.value.hideSelections;

    setItemMessage(itemId, null);

    if (!checkedAt.value) {
        setItemMessage(
            itemId,
            'Не удалось получить состояние позиций. Закройте окно и откройте снова.',
            true,
        );

        return;
    }

    clearingItemId.value = itemId;

    try {
        const response = await api.delete<{ data: Wishlist }>(
            `/v1/wishlists/${props.wishlist.id}/items/${itemId}/selection`,
            { data: { checkedAt: checkedAt.value } },
        );

        item.isSelected = false;
        confirmingItemId.value = null;
        emit('selectionCleared', response.data.data);

        if (isBlind) setItemMessage(itemId, 'Позиция свободна для гостей');
    } catch (error) {
        // Позицию выбрали после checkedAt: окно получает актуальный выбор,
        // а владелец решает заново, уже видя новое состояние
        if (isAxiosError(error) && error.response?.status === 409) {
            confirmingItemId.value = null;
            await refreshSelections();

            setItemMessage(
                itemId,
                isBlind
                    ? 'Состояние позиции изменилось после открытия окна. Если её всё равно нужно освободить, повторите.'
                    : 'Позицию выбрали заново, пока было открыто окно. Выбор не снят.',
                true,
            );

            return;
        }

        console.error('Ошибка снятия выбора гостя:', error);
        setItemMessage(
            itemId,
            'Не удалось снять выбор. Попробуйте ещё раз.',
            true,
        );
    } finally {
        clearingItemId.value = null;
    }
};

const toggleConfirm = (itemId: string): void => {
    setItemMessage(itemId, null);
    confirmingItemId.value = confirmingItemId.value === itemId ? null : itemId;
};

const { itemKey, moveItem } = useItemReorder(() => form.value.items, urlErrors);

const addItem = (): void => {
    form.value.items.push({
        isSelected: false,
        label: '',
        urls: [''],
        priority: null,
        price: null,
    });
};

const removeItem = (index: number): void => {
    form.value.items.splice(index, 1);
    urlErrors.value.splice(index, 1);
};

// Поле ссылки изменено: ошибка этого поля снимается
const setItemUrl = (
    index: number,
    urlIndex: number,
    event: { target: unknown },
): void => {
    if (!(event.target instanceof window.HTMLInputElement)) return;

    const item = form.value.items[index];
    const value = event.target.value;

    item.urls = (item.urls ?? []).map((url, i) =>
        i === urlIndex ? value : url,
    );

    const errors = urlErrors.value[index];

    if (errors) errors[urlIndex] = false;
};

const addItemUrl = (index: number): void => {
    const item = form.value.items[index];

    item.urls = [...(item.urls ?? []), ''];
};

const removeItemUrl = (index: number, urlIndex: number): void => {
    const item = form.value.items[index];

    item.urls = (item.urls ?? []).filter((_, i) => i !== urlIndex);
    urlErrors.value[index]?.splice(urlIndex, 1);
};

// Пустые позиции отбрасываются, ссылки нормализуются; null — в форме есть некорректная ссылка
const prepareItems = (): WishlistItem[] | null => {
    // У дел есть только текст и отметка: ссылку, приоритет и цену сервер для них не сохраняет
    if (isTodo.value) {
        return form.value.items
            .filter((item) => item.label.trim())
            .map((item) => ({
                id: item.id,
                label: item.label,
                isSelected: item.isSelected ?? false,
            }));
    }

    // Ссылки пустых позиций не проверяются: такие позиции не отправляются
    urlErrors.value = form.value.items.map((item) =>
        item.label.trim() ? getItemUrlErrors(item.urls) : [],
    );

    if (urlErrors.value.some((errors) => errors.some(Boolean))) return null;

    // Отметки в списке желаний ставят гости: сервер не принимает их из формы
    return form.value.items
        .filter((item) => item.label.trim())
        .map((item) => ({
            id: item.id,
            label: item.label,
            urls: normalizeItemUrls(item.urls),
            priority: item.priority,
            price: item.price,
        }));
};

// Запрос выполняет WishlistMain (мутация updateWishlist): там же состояние
// загрузки для кнопки. После сохранения окно не закрывается, а получает
// сохранённый список в props.wishlist
const handleSubmit = (): void => {
    // Сохранять нечего: форма совпадает с сохранённым списком
    if (!isDirty.value) return;

    if (isNote.value) {
        if (isNoteEmpty.value) return;

        emit('update', {
            id: props.wishlist.id,
            color: form.value.color,
            content: form.value.content,
        });

        return;
    }

    // Название обязательно у списков желаний и дел: без него сервер список не примет
    if (!form.value.title.trim()) {
        isTitleMissing.value = true;
        return;
    }

    const items = prepareItems();

    if (!items) return;

    emit('update', {
        id: props.wishlist.id,
        title: form.value.title,
        color: form.value.color,
        // У списка дел нет режима сюрприза, а доступ по ссылке меняется только у него
        hideSelections: !isTodo.value && form.value.hideSelections,
        ...(isTodo.value
            ? {
                  isShared: form.value.isShared,
                  guestsCanCheck: form.value.guestsCanCheck,
                  guestNameRequired: form.value.guestNameRequired,
              }
            : {}),
        items,
    });
};

// Блокировка прокрутки при открытии модального окна
const disableBodyScroll = (): void => {
    document.body.classList.add('modal-open');
};

const enableBodyScroll = (): void => {
    document.body.classList.remove('modal-open');
};

onMounted(() => {
    disableBodyScroll();

    // Выбор гостей есть только у списка желаний
    if (!isTodo.value && !isNote.value) void loadSelections(false);
});

onUnmounted(() => {
    enableBodyScroll();
});

const closeModal = (): void => {
    form.value = defaultForm();
    urlErrors.value = [];
    enableBodyScroll();
    emit('close');
};

// Окно закрывается кликом по фону, только если в форме нет несохранённых
// правок (иначе — только крестиком), а нажатие и отпускание кнопки были
// на фоне: выделение текста в поле, законченное за пределами окна, не считается
const isOverlayPressed = ref(false);

const modalEl = useTemplateRef<HTMLElement>('modal');
const saveButtonEl = useTemplateRef<HTMLButtonElement>('saveButton');

const closeOnOverlayClick = (event: MouseEvent): void => {
    const isOverlayClick =
        isOverlayPressed.value && event.target === event.currentTarget;

    isOverlayPressed.value = false;

    if (!isOverlayClick) return;

    if (isDirty.value) {
        nudgeUnsaved(modalEl.value, saveButtonEl.value);

        return;
    }

    closeModal();
};
</script>

<template>
    <div
        class="modal-overlay"
        @mousedown.self="isOverlayPressed = true"
        @click="closeOnOverlayClick"
    >
        <!-- Фон модалки окрашивается в выбранный цвет: превью цвета списка -->
        <div ref="modal" :class="['modal', `wishlist-color--${form.color}`]">
            <div class="modal-header">
                <h2>
                    {{
                        isNote
                            ? 'Редактировать заметку'
                            : 'Редактировать список'
                    }}
                </h2>

                <button class="close-btn" @click="closeModal"></button>
            </div>

            <form @submit.prevent="handleSubmit">
                <div v-if="!isNote" class="form-group">
                    <label>Название списка</label>

                    <input
                        v-model="form.title"
                        type="text"
                        :class="{ 'field--error': isTitleMissing }"
                        :aria-invalid="isTitleMissing"
                        :placeholder="
                            isTodo
                                ? 'Например: Дела на выходные'
                                : 'Например: День рождения'
                        "
                        @input="isTitleMissing = false"
                    />

                    <span
                        v-if="isTitleMissing"
                        class="field-error"
                        role="alert"
                    >
                        Укажите название списка
                    </span>
                </div>

                <div class="form-group">
                    <label>{{ isNote ? 'Цвет заметки' : 'Цвет списка' }}</label>

                    <WishlistColorPicker v-model="form.color" />
                </div>

                <!-- Список желаний доступен по ссылке всегда, а список дел — по выбору владельца -->
                <div v-if="isTodo" class="form-group">
                    <WishlistShareToggle v-model="form.isShared" />

                    <!-- Отмечать дела гости могут только в списке, открытом по ссылке -->
                    <WishlistGuestCheckToggle
                        v-if="form.isShared"
                        v-model="form.guestsCanCheck"
                    />

                    <!-- Имя гостя нужно, только если гости отмечают дела -->
                    <WishlistGuestNameToggle
                        v-if="form.isShared && form.guestsCanCheck"
                        v-model="form.guestNameRequired"
                    />
                </div>

                <!-- Режим сюрприза есть только у списка желаний: у остальных гости ничего не выбирают -->
                <div v-if="!isTodo && !isNote" class="form-group">
                    <WishlistSurpriseToggle v-model="form.hideSelections" />

                    <!-- Ошибка показывается и в режиме сюрприза: без выбора
                         гостей окно не может освободить позицию. Пока выбор
                         загружается, вместо позиций виден лоадер -->
                    <p
                        v-if="selectionError"
                        class="selection-status selection-status--error"
                    >
                        {{ selectionError }}
                    </p>
                </div>

                <div v-if="isNote" class="form-group">
                    <label for="note-content-edit">Текст заметки</label>

                    <textarea
                        id="note-content-edit"
                        v-model="form.content"
                        class="note-content"
                        rows="7"
                        :maxlength="NOTE_CONTENT_MAX_LENGTH"
                    ></textarea>

                    <span class="note-content-counter">
                        {{ form.content.length }} /
                        {{ NOTE_CONTENT_MAX_LENGTH }}
                    </span>
                </div>

                <div v-else class="form-group">
                    <label>{{ isTodo ? 'Дела' : 'Список желаний' }}</label>

                    <!-- Пока загружается выбор гостей, позиции не показываются:
                         отметки и действия с выбором ещё не соответствуют серверу.
                         Правки формы при этом сохраняются -->
                    <div
                        v-if="isSelectionLoading"
                        class="items-loader"
                        role="status"
                        aria-label="Загружаем выбор гостей"
                    >
                        <LoaderButtonSpinner :size="22" />
                    </div>

                    <template v-else>
                        <div
                            v-for="(item, index) in form.items"
                            :key="itemKey(item)"
                            :class="[
                                'wishlist-item',
                                {
                                    'wishlist-item--todo': isTodo,
                                    'wishlist-item--done':
                                        isTodo && item.isSelected,
                                    'wishlist-item--checked-by':
                                        showCheckedByRow,
                                },
                            ]"
                        >
                            <!-- Выполненное дело — галочка на зелёном, как в карточке.
                             Повторный клик снимает отметку -->
                            <label v-if="isTodo" class="checkbox-wrapper">
                                <input
                                    type="checkbox"
                                    v-model="item.isSelected"
                                    class="checkbox-input"
                                    :aria-label="
                                        item.isSelected
                                            ? 'Выполнено, снять отметку'
                                            : 'Отметить как выполненное'
                                    "
                                />

                                <span class="checkbox-custom">
                                    <Check v-if="item.isSelected" :size="12" />
                                </span>
                            </label>

                            <!-- Выбранная гостем позиция — значок подарка на фирменном градиенте.
                             Отметку ставит только гость, поэтому значок не нажимается -->
                            <span
                                v-else-if="showSelection"
                                :class="[
                                    'checkbox-custom',
                                    'selection-mark',
                                    {
                                        'selection-mark--selected':
                                            item.isSelected,
                                    },
                                ]"
                                role="img"
                                :aria-label="
                                    item.isSelected
                                        ? 'Выбрано гостем'
                                        : 'Не выбрано гостями'
                                "
                            >
                                <Gift v-if="item.isSelected" :size="11" />
                            </span>

                            <div class="wishlist-item-fields">
                                <!-- Приоритет над полем описания у левого края,
                                 снятие выбора гостя — у правого -->
                                <div v-if="!isTodo" class="item-meta-row">
                                    <ItemPriorityPicker
                                        v-model="item.priority"
                                        :muted="
                                            showSelection && item.isSelected
                                        "
                                    />

                                    <div
                                        v-if="
                                            showSelection &&
                                            item.isSelected &&
                                            item.id
                                        "
                                        class="clear-selection"
                                    >
                                        <button
                                            type="button"
                                            :class="[
                                                'clear-selection-btn',
                                                {
                                                    'clear-selection-btn--active':
                                                        confirmingItemId ===
                                                        item.id,
                                                },
                                            ]"
                                            :aria-label="`Снять выбор гостя: ${item.label}`"
                                            :aria-expanded="
                                                confirmingItemId === item.id
                                            "
                                            @click="toggleConfirm(item.id)"
                                        >
                                            Снять выбор
                                        </button>
                                    </div>

                                    <!-- Режим сюрприза: освободить позицию, не узнавая,
                                     выбрана ли она. Действие редкое, поэтому скрыто за «⋯» -->
                                    <div
                                        v-else-if="
                                            form.hideSelections && item.id
                                        "
                                        class="clear-selection"
                                    >
                                        <button
                                            type="button"
                                            :class="[
                                                'free-menu-btn',
                                                {
                                                    'free-menu-btn--active':
                                                        confirmingItemId ===
                                                        item.id,
                                                },
                                            ]"
                                            title="Освободить позицию"
                                            :aria-label="`Освободить позицию: ${item.label}`"
                                            :aria-expanded="
                                                confirmingItemId === item.id
                                            "
                                            @click="toggleConfirm(item.id)"
                                        >
                                            <Ellipsis :size="16" />
                                        </button>
                                    </div>
                                </div>

                                <!-- Снятие выбора подтверждается: кнопку можно нажать
                                     случайно, а вернуть выбор гостя владелец не может.
                                     В режиме сюрприза владелец действует вслепую -->
                                <div
                                    v-if="
                                        item.id &&
                                        confirmingItemId === item.id &&
                                        (form.hideSelections ||
                                            (showSelection && item.isSelected))
                                    "
                                    class="free-confirm"
                                >
                                    <p
                                        v-if="form.hideSelections"
                                        class="free-confirm-text"
                                    >
                                        Если эту позицию выбрал гость, его выбор
                                        будет снят. Вы не узнаете, была ли она
                                        выбрана.
                                    </p>
                                    <p v-else class="free-confirm-text">
                                        Снять выбор гостя? Позиция станет
                                        доступна другим гостям, а вернуть выбор
                                        сможет только сам гость.
                                    </p>

                                    <div class="free-confirm-actions">
                                        <button
                                            type="button"
                                            class="clear-selection-btn"
                                            :disabled="clearingItemId !== null"
                                            :aria-busy="
                                                clearingItemId === item.id
                                            "
                                            @click="clearSelection(item)"
                                        >
                                            <span
                                                :class="{
                                                    'clear-selection-text--hidden':
                                                        clearingItemId ===
                                                        item.id,
                                                }"
                                            >
                                                {{
                                                    form.hideSelections
                                                        ? 'Освободить'
                                                        : 'Снять'
                                                }}
                                            </span>

                                            <LoaderButtonSpinner
                                                v-if="
                                                    clearingItemId === item.id
                                                "
                                                class="clear-selection-spinner"
                                                :size="11"
                                            />
                                        </button>

                                        <button
                                            type="button"
                                            class="clear-selection-btn clear-selection-btn--secondary"
                                            :disabled="clearingItemId !== null"
                                            @click="confirmingItemId = null"
                                        >
                                            Отмена
                                        </button>
                                    </div>
                                </div>

                                <p
                                    v-if="item.id && itemMessages[item.id]"
                                    :class="[
                                        'item-selection-message',
                                        {
                                            'item-selection-message--error':
                                                itemMessages[item.id].isError,
                                        },
                                    ]"
                                    :role="
                                        itemMessages[item.id].isError
                                            ? 'alert'
                                            : 'status'
                                    "
                                >
                                    {{ itemMessages[item.id].text }}
                                </p>

                                <!-- Кто отметил дело — над полем, у правого края -->
                                <TodoCheckedBy
                                    v-if="showCheckedByRow"
                                    class="item-checked-by"
                                    :checked-by="formCheckedBy(item)"
                                    :owner-name="ownCheckLabel"
                                    empty-label="Не выполнено"
                                />

                                <input
                                    v-model="item.label"
                                    type="text"
                                    :placeholder="
                                        isTodo
                                            ? 'Например: Купить продукты'
                                            : 'Например: Книга'
                                    "
                                />

                                <!-- Ссылка и цена соединены с полем описания линиями-ветвями:
                                 они относятся к этой позиции -->
                                <div v-if="!isTodo" class="item-branches">
                                    <div
                                        v-for="(url, urlIndex) in item.urls"
                                        :key="urlIndex"
                                        class="item-url-row"
                                    >
                                        <Link
                                            :size="13"
                                            class="item-url-icon"
                                            aria-hidden="true"
                                        />

                                        <!-- type="text", а не "url": иначе браузер не пропустит адрес без https:// -->
                                        <input
                                            :value="url"
                                            type="text"
                                            inputmode="url"
                                            autocomplete="off"
                                            :class="[
                                                'item-url-input',
                                                {
                                                    'item-url-input--error':
                                                        urlErrors[index]?.[
                                                            urlIndex
                                                        ],
                                                },
                                            ]"
                                            :placeholder="
                                                urlIndex === 0
                                                    ? 'Ссылка на товар (необязательно)'
                                                    : 'Ещё одна ссылка'
                                            "
                                            :aria-invalid="
                                                urlErrors[index]?.[urlIndex] ||
                                                undefined
                                            "
                                            @input="
                                                setItemUrl(
                                                    index,
                                                    urlIndex,
                                                    $event,
                                                )
                                            "
                                        />

                                        <!-- Ссылок у позиции до MAX_ITEM_URLS. Справа от поля одно место под кнопку:
                                             «+», пока ссылка одна, и «×», когда их несколько; тогда «+» у последнего
                                             поля выносится правее «×», и ширина полей не меняется -->
                                        <button
                                            v-if="(item.urls?.length ?? 0) > 1"
                                            class="item-url-btn"
                                            type="button"
                                            aria-label="Убрать ссылку"
                                            title="Убрать ссылку"
                                            @click="
                                                removeItemUrl(index, urlIndex)
                                            "
                                        >
                                            <X :size="14" />
                                        </button>

                                        <button
                                            v-if="
                                                urlIndex ===
                                                    (item.urls?.length ?? 0) -
                                                        1 &&
                                                urlIndex < MAX_ITEM_URLS - 1
                                            "
                                            :class="[
                                                'item-url-btn',
                                                {
                                                    'item-url-btn--outside':
                                                        (item.urls?.length ??
                                                            0) > 1,
                                                },
                                            ]"
                                            type="button"
                                            aria-label="Добавить ещё одну ссылку"
                                            title="Добавить ещё одну ссылку"
                                            @click="addItemUrl(index)"
                                        >
                                            <Plus :size="14" />
                                        </button>
                                    </div>

                                    <span
                                        v-if="urlErrors[index]?.some(Boolean)"
                                        class="item-url-error"
                                    >
                                        Некорректная ссылка
                                    </span>

                                    <div class="item-price-row">
                                        <ItemPriceInput
                                            v-model="item.price"
                                            :muted="
                                                showSelection && item.isSelected
                                            "
                                        />
                                    </div>
                                </div>
                            </div>

                            <!-- Перестановка позиций: порядок сохраняется на сервере -->
                            <div v-if="form.items.length > 1" class="move-btns">
                                <button
                                    class="move-btn"
                                    type="button"
                                    aria-label="Переместить выше"
                                    :disabled="index === 0"
                                    @click="moveItem(index, -1, $event)"
                                >
                                    <ChevronUp :size="16" />
                                </button>

                                <button
                                    class="move-btn"
                                    type="button"
                                    aria-label="Переместить ниже"
                                    :disabled="index === form.items.length - 1"
                                    @click="moveItem(index, 1, $event)"
                                >
                                    <ChevronDown :size="16" />
                                </button>
                            </div>

                            <button
                                class="remove-btn"
                                type="button"
                                @click="removeItem(index)"
                            >
                                <Trash2 />
                            </button>
                        </div>

                        <button
                            class="add-item-btn"
                            type="button"
                            @click="addItem"
                        >
                            {{
                                isTodo
                                    ? '+ Добавить дело'
                                    : '+ Добавить желание'
                            }}
                        </button>
                    </template>
                </div>

                <!-- Ошибка сервера, например при потере соединения -->
                <p v-if="props.errorMessage" class="form-error" role="alert">
                    {{ props.errorMessage }}
                </p>

                <button
                    ref="saveButton"
                    type="submit"
                    :disabled="props.isPending || isNoteEmpty || !isDirty"
                    class="create-btn"
                >
                    <LoaderButtonSpinner v-if="props.isPending" :size="18" />

                    <span v-else>Сохранить</span>
                </button>
            </form>
        </div>
    </div>
</template>

<style scoped lang="scss">
@use '../../../scss/ui/checkboxCard';
@use '../../../scss/ui/wishlistModal.scss';
@use '../../../scss/ui/wishlistColors.scss';

// Подпись «кто отметил» над полем дела, у правого края; ближе к полю,
// чем остальные поля позиции друг к другу
.item-checked-by {
    align-self: flex-end;
    margin-bottom: -3px;
}

// Превью цвета списка; для white — прежний белый фон модалки
.modal {
    background: var(--wishlist-bg, #fff);
    transition: background-color 0.2s ease;
}

.wishlist-item input[type='text'] {
    flex-grow: 1;
    width: auto;
}

// По центру поля описания (см. .wishlist-item в wishlistModal.scss)
.wishlist-item > .checkbox-wrapper,
.wishlist-item > .selection-mark {
    flex-shrink: 0;
    margin-top: calc(var(--item-label-offset) + 12px);
}

// Подпись «кто отметил» над полем дела (строка 11px × 1.3 и промежуток 6px − 3px)
// сдвигает поле вниз на 17px: галочка, стрелки и корзина сдвигаются вместе с ним.
// Смещение --item-label-offset задано в wishlistModal.scss, у дела оно равно 0
.wishlist-item.wishlist-item--checked-by {
    --item-label-offset: 17px;
}

// Вместо галочки из checkboxCard.scss — значок на градиенте, как в карточке
.checkbox-input:checked + .checkbox-custom {
    color: #fff;

    &::after {
        content: none;
    }
}

// Отметку гостя владелец только видит: значок не реагирует на наведение
.selection-mark {
    cursor: default;

    &:hover {
        transform: none;
        border-color: rgba(139, 92, 246, 0.35);
    }
}

.selection-mark--selected,
.selection-mark--selected:hover {
    background: var(--brand-gradient);
    border-color: transparent;
    color: #fff;
}

// Снятие выбора гостя: справа в строке приоритета
.clear-selection {
    display: flex;
    align-items: center;
    margin-left: auto;
}

.clear-selection-btn {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 28px;
    padding: 2px 8px;
    border: none;
    border-radius: 8px;
    background: transparent;
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

// Подтверждение для этой позиции открыто
.clear-selection-btn--active {
    color: #fff;
    background: #ec4899;
}

.clear-selection-btn--secondary {
    color: #8a7a99;

    &:hover:not(:disabled) {
        background: #8a7a99;
    }
}

.clear-selection-text--hidden {
    visibility: hidden;
}

// «⋯» в режиме сюрприза: неприметная, как кнопки перестановки позиций
.free-menu-btn {
    display: grid;
    place-items: center;
    width: 24px;
    height: 20px;
    border: none;
    border-radius: 6px;
    background: transparent;
    color: #b3b3b3;
    cursor: pointer;
    transition: all 0.15s ease;

    &:hover,
    &--active {
        color: var(--brand-violet);
        background: rgba(139, 92, 246, 0.1);
    }

    &:focus-visible {
        outline: 2px solid var(--brand-violet);
        outline-offset: -2px;
    }
}

// Подтверждение освобождения позиции в режиме сюрприза
.free-confirm {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 6px 10px;
    padding: 8px 10px;
    border-radius: 10px;
    background: rgba(236, 72, 153, 0.06);
}

.free-confirm-text {
    flex: 1 1 200px;
    margin: 0;
    font-size: 11px;
    line-height: 1.4;
    color: #6b5a7b;
}

.free-confirm-actions {
    display: flex;
    gap: 4px;
}

// Итог снятия выбора под строкой приоритета
.item-selection-message {
    margin: 0;
    font-size: 11px;
    line-height: 1.4;
    color: #8a7a99;
}

.item-selection-message--error {
    color: #e11d48;
}

.clear-selection-spinner {
    position: absolute;
}

// Лоадер на месте позиций, пока загружается выбор гостей
.items-loader {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 120px;
    color: var(--brand-violet);
}

// Состояние выбора гостей под переключателем «Показывать выбранные подарки»
.selection-status {
    margin: 4px 0 0;
    font-size: 11px;
    line-height: 1.4;
    color: #8a7a99;
}

.selection-status--error {
    color: #e11d48;
}

// Выполненное дело: зелёный градиент, как в карточке списка дел
.wishlist-item--todo .checkbox-input:checked + .checkbox-custom {
    background: linear-gradient(135deg, #6ee7b7, #10b981);
}

// Выполненное дело: текст серый и зачёркнут, как в карточке
.wishlist-item--done input[type='text'] {
    color: #94a3b8;
    text-decoration: line-through;
}
</style>
