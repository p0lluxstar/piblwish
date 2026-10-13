<script setup lang="ts">
import { RefreshCw } from '@lucide/vue';
import { ref } from 'vue';

import { api } from '@/lib/api';

import { copyToClipboard } from '../../lib/clipboard';
import { getSharedWishlistUrl } from '../../lib/sharedLink';
import type { Wishlist } from '../../types/wishlist';
import LoaderButtonSpinner from '../ui/LoaderButtonSpinner.vue';

// Смена общей ссылки списка: отдельный запрос, сразу, без сохранения формы.
// Прежняя ссылка перестаёт открывать список, выбор гостей сохраняется
const props = defineProps<{
    wishlist: Wishlist;
}>();

const emit = defineEmits<{
    // Список с новым shareToken, чтобы обновить карточку
    changed: [wishlist: Wishlist];
}>();

const isConfirming = ref(false);
const isPending = ref(false);
const errorMessage = ref<string | null>(null);
// Новая ссылка после смены; её можно сразу скопировать
const newLink = ref<string | null>(null);
const copyStatus = ref<'idle' | 'copied' | 'error'>('idle');

const changeLink = async (): Promise<void> => {
    isPending.value = true;
    errorMessage.value = null;

    try {
        const response = await api.post<{ data: Wishlist }>(
            `/v1/wishlists/${props.wishlist.id}/share-token`,
        );
        const wishlist = response.data.data;

        isConfirming.value = false;
        copyStatus.value = 'idle';
        newLink.value = wishlist.shareToken
            ? getSharedWishlistUrl(wishlist.shareToken, wishlist.type ?? 'gift')
            : null;
        emit('changed', wishlist);
    } catch (error) {
        console.error('Ошибка смены ссылки:', error);
        errorMessage.value = 'Не удалось сменить ссылку. Попробуйте ещё раз.';
    } finally {
        isPending.value = false;
    }
};

const copyNewLink = async (): Promise<void> => {
    if (!newLink.value) return;

    copyStatus.value = (await copyToClipboard(newLink.value))
        ? 'copied'
        : 'error';
};
</script>

<template>
    <div class="share-link-reset">
        <!-- Строка в разделе «Доступ по ссылке», как подсказка переключателя
             (ToggleSwitch): текст слева, кнопка справа. Кнопка только открывает
             предупреждение ниже -->
        <div class="share-link-row">
            <span class="share-link-row-text">
                <span class="share-link-row-hint">
                    <RefreshCw
                        :size="12"
                        class="share-link-row-icon"
                        aria-hidden="true"
                    />
                    Заменить ссылку на новую
                </span>
            </span>

            <button
                type="button"
                :class="[
                    'share-link-reset-btn',
                    { 'share-link-reset-btn--active': isConfirming },
                ]"
                :aria-expanded="isConfirming"
                :disabled="isPending"
                @click="isConfirming = !isConfirming"
            >
                Сменить
            </button>
        </div>

        <div v-if="isConfirming" class="share-link-confirm">
            <p class="share-link-text">
                Прежняя ссылка перестанет открываться, в том числе у гостей,
                которые её уже получили, и в их «Чужих списках». Выбор гостей
                сохранится. Новую ссылку нужно будет отправить заново.
            </p>

            <div class="share-link-actions">
                <button
                    type="button"
                    class="share-link-action"
                    :disabled="isPending"
                    :aria-busy="isPending"
                    @click="changeLink"
                >
                    <span
                        :class="{ 'share-link-action-text--hidden': isPending }"
                    >
                        Сменить
                    </span>

                    <LoaderButtonSpinner
                        v-if="isPending"
                        class="share-link-spinner"
                        :size="11"
                    />
                </button>

                <button
                    type="button"
                    class="share-link-action share-link-action--secondary"
                    :disabled="isPending"
                    @click="isConfirming = false"
                >
                    Отмена
                </button>
            </div>
        </div>

        <p
            v-if="errorMessage"
            class="share-link-status share-link-status--error"
        >
            {{ errorMessage }}
        </p>

        <p v-else-if="newLink" class="share-link-status">
            Ссылка изменена.
            <button type="button" class="share-link-copy" @click="copyNewLink">
                {{
                    copyStatus === 'copied'
                        ? 'Новая ссылка скопирована'
                        : 'Скопировать новую ссылку'
                }}
            </button>
            <span
                v-if="copyStatus === 'error'"
                class="share-link-status--error"
            >
                Не удалось скопировать.
            </span>
        </p>
    </div>
</template>

<style scoped lang="scss">
.share-link-reset {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.share-link-row {
    display: flex;
    align-items: center;
    gap: 8px;
    // Строка продолжает переключатель «Доступ по ссылке», под которым уже
    // есть отступ 6px, поэтому сверху своего отступа нет
    margin-top: -2px;
    padding: 0 0 6px;
}

// Текст как у подсказки ToggleSwitch: значок и строка
.share-link-row-text {
    display: flex;
    flex: 1;
    flex-direction: column;
    gap: 4px;
    min-width: 0;
}

.share-link-row-hint {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 10px;
    line-height: 1.3;
    color: var(--ink-muted);
}

.share-link-row-icon {
    flex-shrink: 0;
    color: var(--brand-violet);
}

.share-link-reset-btn {
    flex-shrink: 0;
    padding: 4px 10px;
    border: none;
    border-radius: 14px;
    background: var(--tint);
    font-size: 11px;
    font-weight: 600;
    color: var(--brand-violet);
    cursor: pointer;
    transition: all 0.18s ease;

    &:hover:not(:disabled),
    &--active {
        color: #fff;
        background: var(--brand-gradient);
    }

    &:disabled {
        cursor: default;
        opacity: 0.6;
    }

    &:focus-visible {
        outline: 2px solid var(--brand-violet);
        outline-offset: 2px;
    }
}

// Подтверждение, как у освобождения позиции в WishlistEditModal
.share-link-confirm {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 6px 10px;
    padding: 8px 10px;
    border-radius: 10px;
    background: rgba(236, 72, 153, 0.06);
}

.share-link-text {
    flex: 1 1 200px;
    margin: 0;
    font-size: 11px;
    line-height: 1.4;
    color: var(--ink-soft);
}

.share-link-actions {
    display: flex;
    gap: 4px;
}

.share-link-action {
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
    color: var(--pink-strong);
    cursor: pointer;
    transition: all 0.18s ease;

    &:hover:not(:disabled) {
        color: #fff;
        background: var(--brand-pink);
    }

    &:disabled {
        cursor: default;
        opacity: 0.6;
    }
}

.share-link-action--secondary {
    color: var(--ink-muted);

    &:hover:not(:disabled) {
        background: var(--ink-muted);
    }
}

.share-link-action-text--hidden {
    visibility: hidden;
}

.share-link-spinner {
    position: absolute;
}

.share-link-status {
    margin: 0;
    font-size: 11px;
    line-height: 1.4;
    color: var(--ink-muted);
}

.share-link-status--error {
    color: var(--danger-strong);
}

.share-link-copy {
    padding: 0;
    border: none;
    background: none;
    font: inherit;
    font-weight: 600;
    color: var(--brand-violet);
    cursor: pointer;

    &:hover {
        text-decoration: underline;
    }
}
</style>
