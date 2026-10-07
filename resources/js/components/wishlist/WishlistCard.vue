<script setup lang="ts">
import {
    Calendar,
    Check,
    CopyPlus,
    ExternalLink,
    EyeOff,
    FileEdit,
    Gift,
    Link,
    ListChecks,
    StickyNote,
    Trash2,
} from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

import { NOTE_CONTENT_MAX_LENGTH } from '../../constants/note';
import { copyToClipboard } from '../../lib/clipboard';
import { formatPrice } from '../../lib/itemPrice';
import { getItemUrlHost } from '../../lib/itemUrl';
import type { Wishlist, WishlistItem } from '../../types/wishlist';
import ItemPriorityHearts from './ItemPriorityHearts.vue';

const props = defineProps<{
    wishlist: Wishlist;
}>();

const emit = defineEmits<{
    edit: [wishlist: Wishlist];
    duplicate: [wishlist: Wishlist];
    delete: [wishlist: Wishlist];
    toggleItem: [wishlist: Wishlist, item: WishlistItem];
    updateContent: [wishlist: Wishlist, content: string];
}>();

// Список дел: нет ссылки для гостей, выполненные дела зачёркнуты
const isTodo = computed(() => props.wishlist.type === 'todo');

// Заметка: вместо названия и позиций текст, нет ссылки для гостей и прогресса
const isNote = computed(() => props.wishlist.type === 'note');

// Ссылка для гостей есть только у списка желаний
const isGift = computed(() => !isTodo.value && !isNote.value);

// «список» или «заметку» в подписях кнопок
const subject = computed(() => (isNote.value ? 'заметку' : 'список'));

const editCard = (): void => {
    emit('edit', props.wishlist);
};

const duplicateCard = (): void => {
    emit('duplicate', props.wishlist);
};

const deleteCard = (): void => {
    emit('delete', props.wishlist);
};

// Отметить дело выполненным или снять отметку без режима редактирования
const toggleItem = (item: WishlistItem): void => {
    emit('toggleItem', props.wishlist, item);
};

// Через сколько миллисекунд после последнего ввода сохраняется текст заметки
const NOTE_SAVE_DELAY = 800;

// Текст заметки редактируется прямо на карточке. Черновик отделён от данных
// списка: пока поле в фокусе, обновление кэша не перезаписывает набранный текст
const noteDraft = ref(props.wishlist.content ?? '');
const isNoteFocused = ref(false);
let noteSaveTimer: number | null = null;

watch(
    () => props.wishlist.content,
    (content) => {
        if (!isNoteFocused.value) {
            noteDraft.value = content ?? '';
        }
    },
);

const clearNoteSaveTimer = (): void => {
    if (noteSaveTimer) {
        window.clearTimeout(noteSaveTimer);
        noteSaveTimer = null;
    }
};

// Пустую заметку сервер не принимает, неизменённый текст сохранять незачем
const saveNote = (): void => {
    clearNoteSaveTimer();

    if (!noteDraft.value.trim() || noteDraft.value === props.wishlist.content) {
        return;
    }

    emit('updateContent', props.wishlist, noteDraft.value);
};

const scheduleNoteSave = (): void => {
    clearNoteSaveTimer();
    noteSaveTimer = window.setTimeout(saveNote, NOTE_SAVE_DELAY);
};

// При уходе из поля текст сохраняется сразу. Стёртый целиком текст
// восстанавливается: заметка удаляется кнопкой с корзиной
const finishNoteEditing = (): void => {
    isNoteFocused.value = false;
    saveNote();

    if (!noteDraft.value.trim()) {
        noteDraft.value = props.wishlist.content ?? '';
    }
};

// Карточка может исчезнуть до истечения задержки (фильтр, переход на другую
// страницу): набранный текст сохраняется сразу
onBeforeUnmount(saveNote);

// Сколько миллисекунд показывать результат копирования
const COPY_FEEDBACK_DURATION = 2000;

const copyStatus = ref<'idle' | 'copied' | 'error'>('idle');
let copyFeedbackTimer: number | null = null;

const showCopyFeedback = (status: 'copied' | 'error'): void => {
    copyStatus.value = status;

    // Повторное нажатие продлевает показ сообщения, а не накапливает таймеры
    if (copyFeedbackTimer) {
        window.clearTimeout(copyFeedbackTimer);
    }

    copyFeedbackTimer = window.setTimeout(() => {
        copyStatus.value = 'idle';
        copyFeedbackTimer = null;
    }, COPY_FEEDBACK_DURATION);
};

const copyLink = async (): Promise<void> => {
    const id = String(props.wishlist.id ?? '');

    if (!id) {
        showCopyFeedback('error');
        return;
    }

    const appUrl = import.meta.env.VITE_API_URL || window.location.origin;
    const fullUrl = `${appUrl}/shared-wishlists/${id}`;

    const isCopied = await copyToClipboard(fullUrl);

    showCopyFeedback(isCopied ? 'copied' : 'error');
};

onBeforeUnmount(() => {
    if (copyFeedbackTimer) {
        window.clearTimeout(copyFeedbackTimer);
    }
});

const selectedCount = computed(
    () => props.wishlist.items.filter((item) => item.isSelected).length,
);

// Целые проценты: без округления 1 из 3 выводилось как 33.333…%
const progress = computed(() => {
    const total = props.wishlist.items.length;

    if (!total) return 0;

    return Math.round((selectedCount.value / total) * 100);
});

// «2 из 5»: сколько подарков выбрали гости или сколько дел выполнено
const progressLabel = computed(
    () => `${selectedCount.value} из ${props.wishlist.items.length}`,
);

// «12 сентября 2026 г.»: на карточке дата выводится со значком календаря
const createdAtDate = computed(() => {
    if (!props.wishlist.createdAt) return '';

    return new Date(props.wishlist.createdAt).toLocaleDateString('ru-RU', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
});

// «Создан 12 сентября 2026 г.»: подсказка и подпись для экранных дикторов
const createdAtLabel = computed(
    () => `${isNote.value ? 'Создана' : 'Создан'} ${createdAtDate.value}`,
);
</script>

<template>
    <div
        :class="[
            'card',
            `wishlist-color--${wishlist.color}`,
            { 'card--todo': isTodo },
        ]"
    >
        <!-- Тип списка виден сразу, даже у пустой карточки: цвет фона выбирает
             пользователь, поэтому тип им не обозначается. Значки те же, что
             на шаге выбора типа при создании -->
        <div class="card-badges">
            <span v-if="isNote" class="card-type card-type--note">
                <StickyNote :size="12" />
                Заметка
            </span>
            <span
                v-else
                :class="[
                    'card-type',
                    isTodo ? 'card-type--todo' : 'card-type--gift',
                ]"
            >
                <ListChecks v-if="isTodo" :size="12" />
                <Gift v-else :size="12" />
                {{ isTodo ? 'Дела' : 'Желания' }}
            </span>

            <!-- Режим сюрприза: выбор гостей скрыт от владельца -->
            <span
                v-if="wishlist.hideSelections"
                class="card-surprise"
                role="img"
                aria-label="Режим сюрприза: выбор гостей скрыт"
                title="Режим сюрприза: выбор гостей скрыт"
            >
                <EyeOff :size="14" />
            </span>
        </div>

        <div class="card-actions">
            <button
                class="card-actions-btn"
                type="button"
                :aria-label="`Редактировать ${subject}`"
                title="Редактировать"
                @click="editCard"
            >
                <FileEdit :size="14" />
            </button>
            <button
                class="card-actions-btn"
                type="button"
                :aria-label="`Сделать дубликат: ${subject}`"
                title="Сделать дубликат"
                @click="duplicateCard"
            >
                <CopyPlus :size="14" />
            </button>
            <!-- Список дел и заметка видны только владельцу: ссылки для гостей у них нет -->
            <div v-if="isGift" class="copy-action">
                <button
                    :class="[
                        'card-actions-btn',
                        {
                            'card-actions-btn--success':
                                copyStatus === 'copied',
                        },
                    ]"
                    type="button"
                    aria-label="Скопировать ссылку на список"
                    @click="copyLink"
                >
                    <Check v-if="copyStatus === 'copied'" :size="14" />
                    <Link v-else :size="14" />
                </button>

                <Transition name="copy-tooltip">
                    <span
                        v-if="copyStatus !== 'idle'"
                        :class="[
                            'copy-tooltip',
                            { 'copy-tooltip--error': copyStatus === 'error' },
                        ]"
                        role="status"
                    >
                        {{
                            copyStatus === 'copied'
                                ? 'Ссылка скопирована'
                                : 'Не удалось скопировать'
                        }}
                    </span>
                </Transition>
            </div>
            <button
                class="card-actions-btn"
                type="button"
                :aria-label="`Удалить ${subject}`"
                title="Удалить"
                @click="deleteCard"
            >
                <Trash2 :size="14" />
            </button>
        </div>

        <!-- Заметка: текст редактируется прямо на карточке и сохраняется
             через NOTE_SAVE_DELAY после ввода или при уходе из поля -->
        <textarea
            v-if="isNote"
            v-model="noteDraft"
            class="note-text"
            rows="3"
            :maxlength="NOTE_CONTENT_MAX_LENGTH"
            aria-label="Текст заметки"
            @focus="isNoteFocused = true"
            @input="scheduleNoteSave"
            @blur="finishNoteEditing"
        ></textarea>

        <div v-else class="card-header">
            <span class="card-title">
                {{ wishlist.title }}
            </span>
        </div>

        <!-- Длинный список прокручивается внутри карточки, как текст заметки,
             а не растягивает весь ряд -->
        <div v-if="!isNote" class="card-items">
            <div
                v-for="(item, itemIndex) in wishlist.items"
                :key="itemIndex"
                :class="[
                    'item',
                    {
                        'item--reserved': !isTodo && item.isSelected,
                        'item--done': isTodo && item.isSelected,
                    },
                ]"
            >
                <!-- Дело отмечается прямо с карточки: выполненное — белая галочка
                 на зелёном фоне, в тон прогрессу списка дел -->
                <button
                    v-if="isTodo"
                    type="button"
                    role="checkbox"
                    :aria-checked="Boolean(item.isSelected)"
                    :aria-label="`${item.isSelected ? 'Снять отметку' : 'Отметить выполненным'}: ${item.label}`"
                    :title="
                        item.isSelected
                            ? 'Снять отметку'
                            : 'Отметить выполненным'
                    "
                    :class="[
                        'todo-toggle',
                        item.isSelected ? 'reserved-icon' : 'checkbox-custom',
                    ]"
                    @click="toggleItem(item)"
                >
                    <Check v-if="item.isSelected" :size="12" />
                </button>

                <!-- Позицию выбрал гость: вместо чекбокса значок подарка -->
                <span
                    v-else-if="item.isSelected"
                    class="reserved-icon"
                    role="img"
                    aria-label="Забронировано"
                    title="Забронировано"
                >
                    <Gift :size="11" />
                </span>

                <!-- Режим сюрприза: выбор скрыт, значок подарка на фиолетовом фоне вместо чекбокса -->
                <span
                    v-else-if="wishlist.hideSelections"
                    class="item-bullet"
                    aria-hidden="true"
                >
                    <Gift :size="11" />
                </span>

                <label v-else class="checkbox-wrapper-disabled">
                    <input
                        type="checkbox"
                        v-model="item.isSelected"
                        disabled
                        class="checkbox-input"
                    />

                    <span class="checkbox-custom"></span>
                </label>

                <span class="item-label">
                    {{ item.label }}
                </span>

                <!-- Приоритет, цена и ссылка — узкой колонкой справа от названия,
                 каждое на своей строке: в одну строку они сильно сужали название -->
                <div
                    v-if="item.priority || item.price != null || item.url"
                    class="item-meta"
                >
                    <ItemPriorityHearts
                        v-if="item.priority"
                        :priority="item.priority"
                        :muted="item.isSelected"
                    />

                    <!-- Стоимость 0 ₽ тоже выводится: null — не указана -->
                    <span v-if="item.price != null" class="item-price">
                        {{ formatPrice(item.price) }}
                    </span>

                    <a
                        v-if="item.url"
                        :href="item.url"
                        target="_blank"
                        rel="noopener noreferrer nofollow"
                        class="item-link"
                        :title="getItemUrlHost(item.url)"
                        :aria-label="`Ссылка на товар: ${getItemUrlHost(item.url)}`"
                    >
                        <ExternalLink :size="13" />
                    </a>
                </div>
            </div>
        </div>

        <!-- Прижат к низу карточки, даже если в ней мало позиций -->
        <div class="card-footer">
            <div
                v-if="!isNote && !wishlist.hideSelections"
                class="card-progress-container"
            >
                <div class="card-progress-info">
                    <span class="progress-label">{{ progressLabel }}</span>
                    <span class="progress-percent">{{ progress }}%</span>
                </div>
                <div class="card-progress">
                    <div
                        class="card-progress-fill"
                        :style="{ width: `${progress}%` }"
                    ></div>
                </div>
            </div>

            <time
                v-if="createdAtDate"
                class="card-created-at"
                :datetime="wishlist.createdAt"
                :title="createdAtLabel"
            >
                <!-- Значок рисуется цветом текста (currentColor); слово «Создан»
                     видно только экранным дикторам -->
                <Calendar :size="12" aria-hidden="true" />
                <span class="sr-only">{{ isNote ? 'Создана' : 'Создан' }}</span>
                {{ createdAtDate }}
            </time>
        </div>
    </div>
</template>

<style scoped lang="scss">
@use '../../../scss/ui/checkboxCard.scss';
@use '../../../scss/ui/wishlistColors.scss';

.card {
    position: relative;
    // Колонка, чтобы .card-footer прижимался к низу; высоту карточек в ряду выравнивает grid
    display: flex;
    flex-direction: column;
    // Цвет списка; без него (white) — прежний полупрозрачный белый фон
    background: var(--wishlist-bg, var(--surface));
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border: 1px solid var(--surface-border);
    border-radius: var(--radius-lg);
    padding: 20px;
    box-shadow: 0 8px 24px -14px rgba(139, 92, 246, 0.25);
    transition:
        transform 0.25s ease,
        box-shadow 0.25s ease;
}

.card:hover {
    transform: translateY(-6px) scale(1.015);
    box-shadow: var(--shadow-glow-lg);
}

.card-actions {
    position: absolute;
    display: flex;
    gap: 6px;
    top: 10px;
    right: 12px;
    font-size: 10px;
    color: #b3b3b3;

    .card-actions-btn {
        display: grid;
        place-items: center;
        width: 24px;
        height: 24px;
        border-radius: 8px;
        transition: all 0.18s ease;

        &:hover,
        &--success {
            color: #fff;
            background: var(--brand-gradient);
            cursor: pointer;
        }
    }
}

.copy-action {
    position: relative;
}

.copy-tooltip {
    position: absolute;
    top: calc(100% + 6px);
    right: 0;
    z-index: 5;
    padding: 5px 10px;
    border-radius: 8px;
    background: var(--brand-gradient);
    box-shadow: var(--shadow-glow);
    font-size: 11px;
    font-weight: 600;
    color: #fff;
    white-space: nowrap;
    pointer-events: none;
}

.copy-tooltip--error {
    background: #ef4444;
}

.copy-tooltip-enter-active,
.copy-tooltip-leave-active {
    transition:
        opacity 0.18s ease,
        transform 0.18s ease;
}

.copy-tooltip-enter-from,
.copy-tooltip-leave-to {
    opacity: 0;
    transform: translateY(-4px);
}

.card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 18px;
    margin-bottom: 10px;
}

.card-title {
    font-size: 15px;
    font-weight: 700;
    color: var(--ink);
}

// Высота позиций и текста заметки, после которой появляется полоса прокрутки
$card-content-max-height: 150px;

// Тонкая полоса прокрутки в фирменных цветах, как у модалки (wishlistModal.scss).
// В Chrome 121+ стандартные scrollbar-width и scrollbar-color отключают
// ::-webkit-scrollbar, поэтому они заданы только для браузеров без него (Firefox)
@mixin card-scrollbar {
    @supports not selector(::-webkit-scrollbar) {
        scrollbar-width: thin;
        scrollbar-color: rgba(139, 92, 246, 0.35) transparent;
    }

    &::-webkit-scrollbar {
        width: 4px;
    }

    &::-webkit-scrollbar-track {
        background: transparent;
    }

    &::-webkit-scrollbar-thumb {
        border-radius: 999px;
        background: rgba(139, 92, 246, 0.3);

        &:hover {
            background: linear-gradient(180deg, #8b5cf6, #ec4899);
        }
    }
}

// Позиции списка: та же высота, что у текста заметки. Полоса прокрутки
// вынесена в правый отступ карточки и не наезжает на цену и ссылку
.card-items {
    max-height: $card-content-max-height;
    overflow-y: auto;
    margin-right: -12px;
    padding-right: 12px;
    @include card-scrollbar;
}

// Значок слева и название по центру позиции по вертикали: колонка справа
// (до трёх строк) бывает выше названия
.item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 5px 0;
    border-bottom: 1px solid rgba(139, 92, 246, 0.1);
    user-select: none;
}

.item:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.item:first-of-type {
    padding-top: 0;
}

// Приоритет, цена и ссылка справа от названия, каждое на своей строке,
// прижаты к правому краю. Строки высотой со значок слева (19px):
// первая строка колонки на одном уровне с первой строкой названия
.item-meta {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    flex-shrink: 0;

    > * {
        min-height: 19px;
        align-items: center;
    }

    .item-price {
        line-height: 19px;
    }

    // Значок ссылки — вровень с правым краем сердец и цены, а не с краем своей кнопки
    .item-link {
        height: 19px;
        margin-right: -4px;
    }
}

.item-label {
    flex: 1;
    min-width: 0;
    font-size: 13px;
    // Чуть плотнее обычного текста: название важнее цены рядом с ним.
    // На Windows 10 у Segoe UI нет начертания 500, там текст остаётся обычным
    font-weight: 500;
    color: var(--ink);
    transition: color 0.15s;
    line-height: 1.35;
    overflow-wrap: anywhere;
}

.item-price {
    flex-shrink: 0;
    font-size: 12px;
    font-weight: 600;
    color: #6b5b7b;
    white-space: nowrap;
}

// Выполненное дело: серый зачёркнутый текст, без фона забронированного подарка
.item.item--done .item-label {
    color: #94a3b8;
    text-decoration: line-through;
}

/* Забронированная позиция: строка с лёгким розовым фоном.
   Отрицательный отступ сохраняет выравнивание значка с чекбоксами соседних строк */
.item.item--reserved {
    margin: 2px -8px;
    padding: 5px 8px;
    border-bottom-color: transparent;
    border-radius: 10px;
    background: rgba(236, 72, 153, 0.06);

    // Серые текст, цена и ссылка в тон значку подарка и сердечкам, как на общей странице
    .item-label,
    .item-price {
        color: #94a3b8;
    }

    .item-link {
        color: #94a3b8;

        &:hover {
            color: #64748b;
            background: rgba(148, 163, 184, 0.22);
        }
    }
}

.item-link {
    display: grid;
    place-items: center;
    flex-shrink: 0;
    width: 22px;
    height: 22px;
    border-radius: 7px;
    color: var(--brand-violet);
    transition: all 0.18s ease;

    &:hover {
        color: #fff;
        background: var(--brand-gradient);
    }
}

.reserved-icon {
    display: grid;
    place-items: center;
    flex-shrink: 0;
    width: 19px;
    height: 19px;
    border-radius: 7px;
    // Фирменный градиент, как у отмеченного чекбокса на общей странице
    background: var(--brand-gradient);
    color: #fff;
}

// Отметка дела кликабельна в обоих состояниях: повторный клик снимает отметку
.todo-toggle {
    cursor: pointer;
    transition: all 0.15s;

    &:hover {
        transform: scale(1.08);
    }

    // Выполненное дело: форма значка выбранного подарка, но зелёный градиент,
    // как у полосы прогресса списка дел
    &.reserved-icon {
        background: linear-gradient(135deg, #6ee7b7, #10b981);
    }

    &.checkbox-custom {
        border-color: rgba(16, 185, 129, 0.45);

        &:hover {
            border-color: #10b981;
        }
    }
}

// Та же форма, что у значка выбранного подарка, но в фиолетовых тонах режима сюрприза
.item-bullet {
    display: grid;
    place-items: center;
    flex-shrink: 0;
    width: 19px;
    height: 19px;
    border-radius: 7px;
    background: linear-gradient(135deg, #ddd6fe, #8b5cf6);
    color: #fff;
}

.card-badges {
    position: absolute;
    top: 10px;
    left: 12px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.card-type {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    height: 22px;
    padding: 0 8px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 600;
    white-space: nowrap;
}

.card-type--gift {
    background: rgba(139, 92, 246, 0.12);
    color: var(--brand-violet);
}

.card-type--todo {
    background: rgba(16, 185, 129, 0.14);
    color: #059669;
}

.card-type--note {
    background: rgba(245, 158, 11, 0.16);
    color: #b45309;
}

// Поле без рамки и фона выглядит как обычный текст карточки. Высота растёт
// вместе с текстом (field-sizing), длинный текст прокручивается внутри
// карточки, а не растягивает весь ряд
.note-text {
    display: block;
    width: 100%;
    // Отступ под метками типа
    margin: 15px 0 0;
    padding: 0;
    max-height: $card-content-max-height;
    overflow-y: auto;
    border: none;
    border-radius: 6px;
    background: transparent;
    // Шрифт «от руки» без наклона, как надпись маркером на бумажном стикере
    font-family: 'Shantell Sans Variable', cursive;
    font-size: 14px;
    font-weight: 500;
    line-height: 1.3;
    color: var(--ink);
    white-space: pre-wrap;
    overflow-wrap: anywhere;
    resize: none;
    field-sizing: content;
    cursor: text;
    @include card-scrollbar;

    &:focus {
        outline: none;
        box-shadow: 0 0 0 4px rgba(139, 92, 246, 0.12);
    }
}

.card-surprise {
    display: grid;
    place-items: center;
    width: 24px;
    height: 24px;
    color: var(--brand-violet);
}

.card-footer {
    margin-top: auto;
    padding-top: 10px;
}

.card-progress {
    width: 100%;
    height: 5px;
    background: rgba(139, 92, 246, 0.1);
    border-radius: 999px;
    overflow: hidden;
}

.card-progress-fill {
    height: 100%;
    background: var(--brand-gradient);
    border-radius: 999px;
    transition: width 0.3s ease;
}

.card-progress-info {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 8px;
}

.progress-label {
    font-size: 11px;
    font-weight: 600;
    color: var(--ink-soft);
}

.progress-percent {
    font-size: 11px;
    font-weight: 700;
    color: var(--brand-violet);
}

// Прогресс списка дел — зелёный, списка желаний — фирменный градиент
.card--todo {
    .progress-percent {
        color: #059669;
    }

    .card-progress {
        background: rgba(16, 185, 129, 0.12);
    }

    .card-progress-fill {
        background: linear-gradient(135deg, #6ee7b7, #10b981);
    }
}

.card-created-at {
    display: flex;
    align-items: center;
    gap: 4px;
    margin-top: 10px;
    font-size: 11px;
    line-height: normal;
    color: #baa7c7;

    // Flex выравнивает значок по центру строки, в которую входит место под
    // выносные элементы букв (у, р), поэтому сами цифры и буквы даты стоят
    // ниже этого центра. Сдвиг опускает значок на их уровень
    svg {
        flex-shrink: 0;
        transform: translateY(1px);
    }
}
</style>
