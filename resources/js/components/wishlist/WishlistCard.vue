<script setup lang="ts">
import { Check, FileEdit, Gift, Link, Trash2 } from '@lucide/vue';
import { computed, onBeforeUnmount, ref } from 'vue';

import type { Wishlist } from '../../types/wishlist';

const props = defineProps<{
    wishlist: Wishlist;
}>();

const emit = defineEmits<{
    edit: [wishlist: Wishlist];
    delete: [wishlist: Wishlist];
}>();

const editCard = (): void => {
    emit('edit', props.wishlist);
};

const deleteCard = (): void => {
    emit('delete', props.wishlist);
};

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

// Запасной способ копирования для небезопасного контекста (HTTP), где Clipboard API недоступен
const copyWithFallback = (text: string): boolean => {
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.left = '-9999px';
    textarea.style.top = '0';
    textarea.setAttribute('aria-hidden', 'true');

    document.body.appendChild(textarea);
    textarea.focus();
    textarea.select();

    try {
        return document.execCommand('copy');
    } catch {
        return false;
    } finally {
        document.body.removeChild(textarea);
    }
};

const copyToClipboard = async (text: string): Promise<boolean> => {
    if (window.navigator.clipboard && window.isSecureContext) {
        try {
            await window.navigator.clipboard.writeText(text);
            return true;
        } catch {
            // Пробуем запасной способ ниже
        }
    }

    return copyWithFallback(text);
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

const progress = computed(() => {
    const items = props.wishlist.items;

    if (!items.length) return 0;

    const selected = items.filter((item) => item.isSelected).length;

    return (selected / items.length) * 100;
});

// «Создан 12 сентября 2026 г.»
const createdAtLabel = computed(() => {
    if (!props.wishlist.createdAt) return '';

    const formatted = new Date(props.wishlist.createdAt).toLocaleDateString(
        'ru-RU',
        { day: 'numeric', month: 'long', year: 'numeric' },
    );

    return `Создан ${formatted}`;
});
</script>

<template>
    <div :class="['card', `wishlist-color--${wishlist.color}`]">
        <div class="card-actions">
            <button class="card-actions-btn" @click="editCard">
                <FileEdit :size="14" />
            </button>
            <div class="copy-action">
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
            <button class="card-actions-btn" @click="deleteCard">
                <Trash2 :size="14" />
            </button>
        </div>
        <div class="card-header">
            <span class="card-title">
                {{ wishlist.title }}
            </span>
        </div>

        <div
            v-for="(item, itemIndex) in wishlist.items"
            :key="itemIndex"
            :class="['item', { 'item--reserved': item.isSelected }]"
        >
            <!-- Позицию выбрал гость: вместо чекбокса значок подарка -->
            <span
                v-if="item.isSelected"
                class="reserved-icon"
                role="img"
                aria-label="Забронировано"
                title="Забронировано"
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
        </div>

        <!-- Прижат к низу карточки, даже если в ней мало позиций -->
        <div class="card-footer">
            <div class="card-progress-container">
                <span class="progress-percent">{{ progress }}%</span>
                <div class="card-progress">
                    <div
                        class="card-progress-fill"
                        :style="{ width: `${progress}%` }"
                    ></div>
                </div>
            </div>

            <time
                v-if="createdAtLabel"
                class="card-created-at"
                :datetime="wishlist.createdAt"
            >
                {{ createdAtLabel }}
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
    margin-top: 10px;
    margin-bottom: 16px;
}

.card-title {
    font-size: 15px;
    font-weight: 700;
    color: var(--ink);
}

.item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 7px 0;
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

.item-label {
    flex: 1;
    min-width: 0;
    font-size: 13px;
    color: var(--ink);
    transition: color 0.15s;
    line-height: 1.35;
    overflow-wrap: anywhere;
}

/* Забронированная позиция: строка с лёгким розовым фоном.
   Отрицательный отступ сохраняет выравнивание значка с чекбоксами соседних строк */
.item.item--reserved {
    margin: 2px -8px;
    padding: 7px 8px;
    border-bottom-color: transparent;
    border-radius: 10px;
    background: rgba(236, 72, 153, 0.06);
}

.reserved-icon {
    display: grid;
    place-items: center;
    flex-shrink: 0;
    width: 19px;
    height: 19px;
    border-radius: 7px;
    background: var(--brand-gradient);
    color: #fff;
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

.progress-percent {
    display: inline-block;
    width: 100%;
    font-size: 11px;
    font-weight: 700;
    color: var(--brand-violet);
    min-width: 45px;
    text-align: right;
}

.card-created-at {
    display: block;
    margin-top: 10px;
    font-size: 11px;
    color: #baa7c7;
}
</style>
