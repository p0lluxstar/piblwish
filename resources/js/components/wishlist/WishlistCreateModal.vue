<script setup lang="ts">
import {
    ArrowLeft,
    ChevronDown,
    ChevronUp,
    Gift,
    Link,
    ListChecks,
    Plus,
    StickyNote,
    Trash2,
    X,
} from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref, useTemplateRef } from 'vue';

import { useItemReorder } from '../../composables/useItemReorder';
import { NOTE_CONTENT_MAX_LENGTH } from '../../constants/note';
import {
    getItemUrlErrors,
    MAX_ITEM_URLS,
    normalizeItemUrls,
} from '../../lib/itemUrl';
import { nudgeUnsaved } from '../../lib/nudgeUnsaved';
import type {
    Wishlist,
    WishlistCreatePayload,
    WishlistForm,
    WishlistItem,
    WishlistType,
} from '../../types/wishlist';
import LoaderButtonSpinner from '../ui/LoaderButtonSpinner.vue';
import ItemPriceInput from './ItemPriceInput.vue';
import ItemPriorityPicker from './ItemPriorityPicker.vue';
import WishlistColorPicker from './WishlistColorPicker.vue';
import WishlistGuestCheckToggle from './WishlistGuestCheckToggle.vue';
import WishlistGuestNameToggle from './WishlistGuestNameToggle.vue';
import WishlistShareToggle from './WishlistShareToggle.vue';
import WishlistSurpriseToggle from './WishlistSurpriseToggle.vue';

const props = defineProps<{
    isPending: boolean;
    // Текст ошибки сервера после неудачной попытки создать список
    errorMessage?: string | null;
    // Список-источник при дублировании: форма заполняется его значениями
    source?: Wishlist | null;
}>();

const emit = defineEmits<{
    close: [];
    create: [payload: WishlistCreatePayload];
}>();

const defaultForm = (type: WishlistType = 'gift'): WishlistForm => ({
    type,
    title: '',
    content: '',
    // Заметка по умолчанию жёлтая, как бумажный стикер
    color: type === 'note' ? 'lemon' : 'white',
    // Выбор гостей, однажды увиденный владельцем, уже не скрыть,
    // поэтому список желаний по умолчанию создаётся в режиме сюрприза
    hideSelections: true,
    // Список дел по умолчанию личный: доступ по ссылке владелец включает сам
    isShared: false,
    guestsCanCheck: false,
    // Имя гостя по умолчанию обязательно: ради подписей под делами отметки и включают
    guestNameRequired: true,
    items: [
        {
            label: '',
            urls: [''],
            priority: null,
            price: null,
            isSelected: false,
        },
    ],
});

// Копия списка-источника без id позиций и выбора гостей: создаётся новый список
const sourceForm = (source: Wishlist): WishlistForm => ({
    type: source.type ?? 'gift',
    // У заметки названия нет, поэтому нет и пометки «(копия)»
    title: source.title ? `${source.title} (копия)` : '',
    content: source.content ?? '',
    color: source.color,
    hideSelections: source.hideSelections ?? false,
    // Копия списка дел создаётся личной: ссылку на неё владелец ещё никому не давал
    isShared: false,
    guestsCanCheck: source.guestsCanCheck ?? false,
    guestNameRequired: source.guestNameRequired ?? true,
    items: source.items.length
        ? source.items.map((item) => ({
              label: item.label,
              urls: item.urls?.length ? [...item.urls] : [''],
              priority: item.priority ?? null,
              price: item.price ?? null,
              isSelected: false,
          }))
        : defaultForm().items,
});

const form = ref(props.source ? sourceForm(props.source) : defaultForm());

// Сначала выбирается тип списка; при дублировании тип берётся у списка-источника
const step = ref<'type' | 'form'>(props.source ? 'form' : 'type');

// Форма в том виде, в котором её сохранит сервер: пустые позиции отброшены,
// ссылки нормализованы, пробелы по краям текста не учитываются
const formSnapshot = (value: WishlistForm): string =>
    JSON.stringify({
        type: value.type,
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
                label: item.label.trim(),
                urls: normalizeItemUrls(item.urls),
                priority: item.priority ?? null,
                price: item.price ?? null,
            })),
    });

// Снимок формы на момент перехода к ней: пустой формы выбранного типа
// или копии списка-источника при дублировании
const initialSnapshot = ref(formSnapshot(form.value));

// В форму что-то введено: клик по фону окно не закрывает
const isDirty = computed(
    () => formSnapshot(form.value) !== initialSnapshot.value,
);

const isTodo = computed(() => form.value.type === 'todo');

// Заметка: вместо названия и позиций одно текстовое поле
const isNote = computed(() => form.value.type === 'note');

const modalTitle = computed(() => {
    if (props.source) {
        return isNote.value ? 'Дублировать заметку' : 'Дублировать список';
    }

    if (step.value === 'type') return 'Новая карточка';
    if (isNote.value) return 'Новая заметка';

    return isTodo.value ? 'Новый список дел' : 'Новый список желаний';
});

// Пустую заметку сервер не принимает, поэтому кнопка создания недоступна
const isNoteEmpty = computed(() => isNote.value && !form.value.content.trim());

// Название списка не заполнено: ошибка выводится под полем после попытки отправки
// и снимается, как только название начинают вводить
const isTitleMissing = ref(false);

// Ошибки полей ссылок по индексам позиций; ошибка снимается, когда ссылку начинают править
const urlErrors = ref<boolean[][]>([]);

const { itemKey, moveItem } = useItemReorder(() => form.value.items, urlErrors);

const addItem = (): void => {
    form.value.items.push({
        label: '',
        urls: [''],
        priority: null,
        price: null,
        isSelected: false,
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

const chooseType = (type: WishlistType): void => {
    form.value = defaultForm(type);
    initialSnapshot.value = formSnapshot(form.value);
    urlErrors.value = [];
    isTitleMissing.value = false;
    step.value = 'form';
};

const backToTypeChoice = (): void => {
    step.value = 'type';
};

// Пустые позиции отбрасываются, ссылки нормализуются; null — в форме есть некорректная ссылка.
// У дел есть только текст: ссылку, приоритет и цену сервер для них не принимает
const prepareItems = (): WishlistItem[] | null => {
    if (isTodo.value) {
        return form.value.items
            .filter((item) => item.label.trim())
            .map((item) => ({ label: item.label, isSelected: false }));
    }

    // Ссылки пустых позиций не проверяются: такие позиции не отправляются
    urlErrors.value = form.value.items.map((item) =>
        item.label.trim() ? getItemUrlErrors(item.urls) : [],
    );

    if (urlErrors.value.some((errors) => errors.some(Boolean))) return null;

    return form.value.items
        .filter((item) => item.label.trim())
        .map((item) => ({ ...item, urls: normalizeItemUrls(item.urls) }));
};

const handleSubmit = (): void => {
    // У заметки нет названия, позиций и режима сюрприза: сервер их не принимает
    if (isNote.value) {
        if (isNoteEmpty.value) return;

        emit('create', {
            type: 'note',
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

    emit('create', {
        type: form.value.type,
        title: form.value.title,
        color: form.value.color,
        // У списка дел нет режима сюрприза, а доступ по ссылке включается только у него
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

const disableBodyScroll = (): void => {
    document.body.classList.add('modal-open');
};

const enableBodyScroll = (): void => {
    document.body.classList.remove('modal-open');
};

onMounted(() => {
    disableBodyScroll();
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

// Кликом по фону окно закрывается, пока в форму ничего не введено (в том числе
// на шаге выбора типа); заполненную форму закрывает только крестик, а окно
// вздрагивает и подсвечивает кнопку создания. Нажатие и отпускание кнопки
// мыши должны быть на фоне, как в окне редактирования
const isOverlayPressed = ref(false);

const modalEl = useTemplateRef<HTMLElement>('modal');
const submitButtonEl = useTemplateRef<HTMLButtonElement>('submitButton');

const closeOnOverlayClick = (event: MouseEvent): void => {
    const isOverlayClick =
        isOverlayPressed.value && event.target === event.currentTarget;

    isOverlayPressed.value = false;

    if (!isOverlayClick) return;

    if (step.value === 'form' && isDirty.value) {
        nudgeUnsaved(modalEl.value, submitButtonEl.value);

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
                <h2>{{ modalTitle }}</h2>

                <button class="close-btn" @click="closeModal"></button>
            </div>

            <!-- Первый шаг: выбор типа списка -->
            <div v-if="step === 'type'" class="type-choice">
                <button
                    class="type-option"
                    type="button"
                    @click="chooseType('gift')"
                >
                    <span class="type-option-icon">
                        <Gift :size="22" />
                    </span>

                    <span class="type-option-text">
                        <span class="type-option-title">Список желаний</span>
                        <span class="type-option-description">
                            Подарки со ссылками и ценами; друзья отмечают их по
                            ссылке
                        </span>
                    </span>
                </button>

                <button
                    class="type-option"
                    type="button"
                    @click="chooseType('todo')"
                >
                    <span class="type-option-icon">
                        <ListChecks :size="22" />
                    </span>

                    <span class="type-option-text">
                        <span class="type-option-title">Список дел</span>
                        <span class="type-option-description">
                            Задачи, которые вы отмечаете выполненными; списком
                            можно поделиться по ссылке для просмотра
                        </span>
                    </span>
                </button>

                <button
                    class="type-option"
                    type="button"
                    @click="chooseType('note')"
                >
                    <span class="type-option-icon">
                        <StickyNote :size="22" />
                    </span>

                    <span class="type-option-text">
                        <span class="type-option-title">Заметка</span>
                        <span class="type-option-description">
                            Произвольный текст на цветном стикере; заметка видна
                            только вам
                        </span>
                    </span>
                </button>
            </div>

            <form v-else @submit.prevent="handleSubmit">
                <button
                    v-if="!props.source"
                    class="back-btn"
                    type="button"
                    @click="backToTypeChoice"
                >
                    <ArrowLeft :size="14" />
                    Выбрать другой тип списка
                </button>

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

                <!-- Режим сюрприза есть только у списка желаний: у остальных гости ничего не выбирают -->
                <div v-if="form.type === 'gift'" class="form-group">
                    <WishlistSurpriseToggle v-model="form.hideSelections" />
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

                <div v-if="isNote" class="form-group">
                    <label for="note-content">Текст заметки</label>

                    <textarea
                        id="note-content"
                        v-model="form.content"
                        class="note-content"
                        rows="7"
                        :maxlength="NOTE_CONTENT_MAX_LENGTH"
                        placeholder="Например: код домофона, список покупок или поздравление"
                    ></textarea>

                    <span class="note-content-counter">
                        {{ form.content.length }} /
                        {{ NOTE_CONTENT_MAX_LENGTH }}
                    </span>
                </div>

                <div v-else class="form-group">
                    <label>{{ isTodo ? 'Дела' : 'Список желаний' }}</label>

                    <div
                        v-for="(item, index) in form.items"
                        :key="itemKey(item)"
                        :class="[
                            'wishlist-item',
                            { 'wishlist-item--todo': isTodo },
                        ]"
                    >
                        <div class="wishlist-item-fields">
                            <!-- Приоритет над полем описания у левого края -->
                            <div v-if="!isTodo" class="item-meta-row">
                                <ItemPriorityPicker v-model="item.priority" />
                            </div>

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
                                            setItemUrl(index, urlIndex, $event)
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
                                        @click="removeItemUrl(index, urlIndex)"
                                    >
                                        <X :size="14" />
                                    </button>

                                    <button
                                        v-if="
                                            urlIndex ===
                                                (item.urls?.length ?? 0) - 1 &&
                                            urlIndex < MAX_ITEM_URLS - 1
                                        "
                                        :class="[
                                            'item-url-btn',
                                            {
                                                'item-url-btn--outside':
                                                    (item.urls?.length ?? 0) >
                                                    1,
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
                                    <ItemPriceInput v-model="item.price" />
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

                    <button class="add-item-btn" type="button" @click="addItem">
                        {{ isTodo ? '+ Добавить дело' : '+ Добавить желание' }}
                    </button>
                </div>

                <!-- Ошибка сервера, например при потере соединения -->
                <p v-if="props.errorMessage" class="form-error" role="alert">
                    {{ props.errorMessage }}
                </p>

                <button
                    ref="submitButton"
                    type="submit"
                    :disabled="props.isPending || isNoteEmpty"
                    class="create-btn"
                >
                    <LoaderButtonSpinner v-if="props.isPending" :size="18" />

                    <span v-else>Создать</span>
                </button>
            </form>
        </div>
    </div>
</template>

<style scoped lang="scss">
@use '../../../scss/ui/wishlistModal.scss';
@use '../../../scss/ui/wishlistColors.scss';

// Превью цвета списка; для white — прежний белый фон модалки
.modal {
    background: var(--wishlist-bg, #fff);
    transition: background-color 0.2s ease;
}

// Выбор типа списка: две карточки-кнопки друг под другом
.type-choice {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.type-option {
    display: flex;
    align-items: center;
    gap: 14px;
    width: 100%;
    padding: 16px;
    border: 1.5px solid rgba(139, 92, 246, 0.2);
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.6);
    text-align: left;
    cursor: pointer;
    transition: all 0.18s ease;

    &:hover {
        border-color: var(--brand-violet);
        box-shadow: 0 8px 20px -12px rgba(139, 92, 246, 0.5);
        transform: translateY(-2px);
    }

    &:focus-visible {
        outline: 2px solid var(--brand-violet);
        outline-offset: 2px;
    }
}

.type-option-icon {
    display: grid;
    place-items: center;
    flex-shrink: 0;
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: var(--brand-gradient);
    color: #fff;
}

.type-option-text {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.type-option-title {
    font-size: 15px;
    font-weight: 700;
    color: var(--ink);
}

.type-option-description {
    font-size: 12px;
    color: #6b5b7b;
}

.back-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 14px;
    padding: 0;
    border: none;
    background: none;
    font-size: 12px;
    color: var(--brand-violet);
    cursor: pointer;

    &:hover {
        text-decoration: underline;
    }
}
</style>
