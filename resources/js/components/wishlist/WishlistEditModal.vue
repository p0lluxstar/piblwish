<script setup lang="ts">
import { Check, ChevronDown, ChevronUp, Gift, Link, Trash2 } from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

import { api } from '@/lib/api';

import { useItemReorder } from '../../composables/useItemReorder';
import { NOTE_CONTENT_MAX_LENGTH } from '../../constants/note';
import { isValidItemUrl, normalizeItemUrl } from '../../lib/itemUrl';
import type {
    Wishlist,
    WishlistForm,
    WishlistItem,
    WishlistUpdatePayload,
} from '../../types/wishlist';
import LoaderButtonSpinner from '../ui/LoaderButtonSpinner.vue';
import ItemPriceInput from './ItemPriceInput.vue';
import ItemPriorityPicker from './ItemPriorityPicker.vue';
import WishlistColorPicker from './WishlistColorPicker.vue';
import WishlistSurpriseToggle from './WishlistSurpriseToggle.vue';

const props = defineProps<{
    wishlist: Wishlist;
    isPending: boolean;
}>();

const emit = defineEmits<{
    close: [];
    update: [payload: WishlistUpdatePayload];
}>();

// Тип списка при редактировании не меняется, поэтому в форме его нет
type EditForm = Omit<WishlistForm, 'type'>;

const defaultForm = (): EditForm => ({
    title: '',
    content: '',
    color: 'white',
    hideSelections: false,
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
    items: [],
});

// Индексы позиций с некорректной ссылкой; ошибка снимается, когда ссылку начинают править
const urlErrors = ref<boolean[]>([]);

// Выбор гостей, полученный после выключения режима сюрприза в этом окне
const isSelectionRevealed = ref(false);
const isSelectionLoading = ref(false);
const selectionError = ref<string | null>(null);

// Форма в том виде, в котором её сохранит сервер: пустые позиции отброшены,
// ссылки нормализованы, пробелы по краям текста не учитываются.
// При ignoreSelection отметки выбора не сравниваются: в режиме сюрприза
// сервер их не принимает, а раскрытый в окне выбор изменением не считается
const formSnapshot = (value: EditForm, ignoreSelection: boolean): string =>
    JSON.stringify({
        title: value.title.trim(),
        content: value.content.trim(),
        color: value.color,
        hideSelections: value.hideSelections,
        items: value.items
            .filter((item) => item.label.trim())
            .map((item) => ({
                id: item.id ?? null,
                label: item.label.trim(),
                url: normalizeItemUrl(item.url),
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

        form.value = {
            title: wishlist.title ?? '',
            content: wishlist.content ?? '',
            color: wishlist.color,
            hideSelections: wishlist.hideSelections ?? false,
            // id нужен серверу, чтобы в режиме сюрприза сохранить выбор гостей
            items: wishlist.items.map((item) => ({
                id: item.id,
                label: item.label,
                url: item.url ?? '',
                priority: item.priority ?? null,
                price: item.price ?? null,
                isSelected: item.isSelected ?? false,
            })),
        };

        initialSnapshot.value = formSnapshot(
            form.value,
            Boolean(wishlist.hideSelections),
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
        formSnapshot(form.value, Boolean(props.wishlist.hideSelections)) !==
        initialSnapshot.value,
);

// Список дел: у позиций только текст и отметка «выполнено»
const isTodo = computed(() => props.wishlist.type === 'todo');

// Заметка: изменяются только цвет и текст
const isNote = computed(() => props.wishlist.type === 'note');

// Пустую заметку сервер не принимает, поэтому кнопка сохранения недоступна
const isNoteEmpty = computed(() => isNote.value && !form.value.content.trim());

// Режим сюрприза был включён при открытии окна: сервер не прислал isSelected,
// а при сохранении не примет его из формы
const wasSelectionHidden = computed(() => Boolean(props.wishlist.hideSelections));

// Владелец выключил режим сюрприза: выбор гостей запрашивается сразу,
// чтобы показать его до сохранения списка
const revealSelections = async (): Promise<void> => {
    isSelectionLoading.value = true;
    selectionError.value = null;

    try {
        const response = await api.get<{ data: { itemIds: string[] } }>(
            `/v1/wishlists/${props.wishlist.id}/selections`,
        );
        const selectedIds = new Set(response.data.data.itemIds);

        // Позиции, добавленные в этом окне, ещё не сохранены и выбраны быть не могут
        form.value.items.forEach((item) => {
            item.isSelected = Boolean(item.id && selectedIds.has(item.id));
        });

        isSelectionRevealed.value = true;
    } catch (error) {
        console.error('Ошибка загрузки выбора гостей:', error);
        selectionError.value =
            'Не удалось загрузить выбор гостей. Он будет виден после сохранения списка.';
    } finally {
        isSelectionLoading.value = false;
    }
};

watch(
    () => form.value.hideSelections,
    (hideSelections) => {
        if (
            !hideSelections &&
            wasSelectionHidden.value &&
            !isSelectionRevealed.value &&
            !isSelectionLoading.value
        ) {
            void revealSelections();
        }
    },
);

// Чекбоксы выбора показываются, только если владелец видит выбор гостей
const showSelection = computed(
    () =>
        !form.value.hideSelections &&
        (!wasSelectionHidden.value || isSelectionRevealed.value),
);

// Выбор, раскрытый в этом окне, только показывается: сервер не учитывает isSelected,
// пока список сохранён в режиме сюрприза. Изменить отметки можно после сохранения
const isSelectionReadonly = computed(() => wasSelectionHidden.value);

const { itemKey, moveItem } = useItemReorder(() => form.value.items, urlErrors);

const addItem = (): void => {
    form.value.items.push({
        isSelected: false,
        label: '',
        url: '',
        priority: null,
        price: null,
    });
};

const removeItem = (index: number): void => {
    form.value.items.splice(index, 1);
    urlErrors.value.splice(index, 1);
};

const clearUrlError = (index: number): void => {
    urlErrors.value[index] = false;
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

    urlErrors.value = form.value.items.map((item) => {
        const url = normalizeItemUrl(item.url);

        return Boolean(item.label.trim() && url && !isValidItemUrl(url));
    });

    if (urlErrors.value.some(Boolean)) return null;

    return form.value.items
        .filter((item) => item.label.trim())
        .map((item) => ({ ...item, url: normalizeItemUrl(item.url) }));
};

// Запрос выполняет WishlistMain (мутация updateWishlist): там же состояние
// загрузки для кнопки и закрытие модалки после успешного сохранения
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

    const items = prepareItems();

    if (!items) return;

    emit('update', {
        id: props.wishlist.id,
        title: form.value.title,
        color: form.value.color,
        // У списка дел нет гостей и режима сюрприза
        hideSelections: !isTodo.value && form.value.hideSelections,
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
                <div v-if="!isTodo && !isNote" class="form-group">
                    <WishlistSurpriseToggle v-model="form.hideSelections" />

                    <p
                        v-if="
                            !form.hideSelections &&
                            (isSelectionLoading || selectionError)
                        "
                        :class="[
                            'selection-status',
                            { 'selection-status--error': selectionError },
                        ]"
                    >
                        {{ selectionError ?? 'Загружаем выбор гостей…' }}
                    </p>
                    <p
                        v-else-if="showSelection && isSelectionReadonly"
                        class="selection-status"
                    >
                        Изменить отметки можно будет после сохранения списка.
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

                    <div
                        v-for="(item, index) in form.items"
                        :key="itemKey(item)"
                        :class="[
                            'wishlist-item',
                            {
                                'wishlist-item--todo': isTodo,
                                'wishlist-item--done':
                                    isTodo && item.isSelected,
                            },
                        ]"
                    >
                        <!-- Выбранная гостем позиция — значок на фирменном градиенте, выполненное
                             дело — на зелёном, как в карточке. Повторный клик снимает отметку -->
                        <label v-if="showSelection" class="checkbox-wrapper">
                            <input
                                type="checkbox"
                                v-model="item.isSelected"
                                class="checkbox-input"
                                :disabled="isSelectionReadonly"
                                :aria-label="
                                    isTodo
                                        ? item.isSelected
                                            ? 'Выполнено, снять отметку'
                                            : 'Отметить как выполненное'
                                        : item.isSelected
                                          ? 'Забронировано, снять выбор'
                                          : 'Отметить как выбранное'
                                "
                            />

                            <span class="checkbox-custom">
                                <template v-if="item.isSelected">
                                    <Check v-if="isTodo" :size="12" />
                                    <Gift v-else :size="11" />
                                </template>
                            </span>
                        </label>

                        <div class="wishlist-item-fields">
                            <!-- Приоритет над полем описания у левого края -->
                            <div v-if="!isTodo" class="item-meta-row">
                                <ItemPriorityPicker
                                    v-model="item.priority"
                                    :muted="showSelection && item.isSelected"
                                />
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
                                <div class="item-url-row">
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

                    <button class="add-item-btn" type="button" @click="addItem">
                        {{ isTodo ? '+ Добавить дело' : '+ Добавить желание' }}
                    </button>
                </div>

                <button
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
.wishlist-item > .checkbox-wrapper {
    flex-shrink: 0;
    margin-top: calc(var(--item-label-offset) + 12px);
}

// Вместо галочки из checkboxCard.scss — значок подарка на фирменном градиенте, как в карточке
.checkbox-input:checked + .checkbox-custom {
    background: var(--brand-gradient);
    color: #fff;

    &::after {
        content: none;
    }
}

// Выбор, раскрытый до сохранения, только показывается: отмеченный подарок сохраняет
// фирменный градиент, а не серый фон недоступного чекбокса из checkboxCard.scss
.checkbox-input:disabled + .checkbox-custom {
    cursor: default;
}

.checkbox-input:disabled:checked + .checkbox-custom {
    background: var(--brand-gradient);
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
