<script setup lang="ts">
import {
    ArrowLeft,
    ChevronDown,
    ChevronUp,
    Gift,
    Link,
    ListChecks,
    StickyNote,
    Trash2,
} from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref } from 'vue';

import { useItemReorder } from '../../composables/useItemReorder';
import { NOTE_CONTENT_MAX_LENGTH } from '../../constants/note';
import { isValidItemUrl, normalizeItemUrl } from '../../lib/itemUrl';
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
import WishlistSurpriseToggle from './WishlistSurpriseToggle.vue';

const props = defineProps<{
    isPending: boolean;
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
    hideSelections: false,
    items: [
        {
            label: '',
            url: '',
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
    items: source.items.length
        ? source.items.map((item) => ({
              label: item.label,
              url: item.url ?? '',
              priority: item.priority ?? null,
              price: item.price ?? null,
              isSelected: false,
          }))
        : defaultForm().items,
});

const form = ref(props.source ? sourceForm(props.source) : defaultForm());

// Сначала выбирается тип списка; при дублировании тип берётся у списка-источника
const step = ref<'type' | 'form'>(props.source ? 'form' : 'type');

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

// Индексы позиций с некорректной ссылкой; ошибка снимается, когда ссылку начинают править
const urlErrors = ref<boolean[]>([]);

const { itemKey, moveItem } = useItemReorder(() => form.value.items, urlErrors);

const addItem = (): void => {
    form.value.items.push({
        label: '',
        url: '',
        priority: null,
        price: null,
        isSelected: false,
    });
};

const removeItem = (index: number): void => {
    form.value.items.splice(index, 1);
    urlErrors.value.splice(index, 1);
};

const clearUrlError = (index: number): void => {
    urlErrors.value[index] = false;
};

const chooseType = (type: WishlistType): void => {
    form.value = defaultForm(type);
    urlErrors.value = [];
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

    urlErrors.value = form.value.items.map((item) => {
        const url = normalizeItemUrl(item.url);

        return Boolean(item.label.trim() && url && !isValidItemUrl(url));
    });

    if (urlErrors.value.some(Boolean)) return null;

    return form.value.items
        .filter((item) => item.label.trim())
        .map((item) => ({ ...item, url: normalizeItemUrl(item.url) }));
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

    const items = prepareItems();

    if (!items) return;

    emit('create', {
        type: form.value.type,
        title: form.value.title,
        color: form.value.color,
        // У списка дел нет гостей и режима сюрприза
        hideSelections: !isTodo.value && form.value.hideSelections,
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
</script>

<template>
    <div class="modal-overlay">
        <!-- Фон модалки окрашивается в выбранный цвет: превью цвета списка -->
        <div :class="['modal', `wishlist-color--${form.color}`]">
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
                            Задачи, которые вы отмечаете выполненными; список
                            виден только вам
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
                        :placeholder="
                            isTodo
                                ? 'Например: Дела на выходные'
                                : 'Например: День рождения'
                        "
                    />
                </div>

                <div class="form-group">
                    <label>{{ isNote ? 'Цвет заметки' : 'Цвет списка' }}</label>

                    <WishlistColorPicker v-model="form.color" />
                </div>

                <!-- Режим сюрприза есть только у списка желаний: у остальных нет гостей -->
                <div v-if="form.type === 'gift'" class="form-group">
                    <WishlistSurpriseToggle v-model="form.hideSelections" />
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
                            <!-- Приоритет над полем описания у левого края, цена — у правого -->
                            <div v-if="!isTodo" class="item-meta-row">
                                <ItemPriorityPicker v-model="item.priority" />

                                <ItemPriceInput v-model="item.price" />
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

                            <!-- Линия-уголок от поля описания: ссылка относится к этой позиции -->
                            <div v-if="!isTodo" class="item-url-row">
                                <Link
                                    :size="13"
                                    class="item-url-icon"
                                    aria-hidden="true"
                                />

                                <!-- type="text", а не "url": иначе браузер не пропустит адрес без https:// -->
                                <input
                                    v-model="item.url"
                                    type="text"
                                    inputmode="url"
                                    autocomplete="off"
                                    :class="[
                                        'item-url-input',
                                        {
                                            'item-url-input--error':
                                                urlErrors[index],
                                        },
                                    ]"
                                    placeholder="Ссылка на товар (необязательно)"
                                    :aria-invalid="
                                        urlErrors[index] || undefined
                                    "
                                    @input="clearUrlError(index)"
                                />
                            </div>

                            <span
                                v-if="urlErrors[index]"
                                class="item-url-error"
                            >
                                Некорректная ссылка
                            </span>
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

                <button
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
