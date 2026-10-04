<script setup lang="ts">
import { Check } from '@lucide/vue';

import { WISHLIST_COLORS } from '@/constants/wishlistColors';
import type { WishlistColor } from '@/types/wishlist';

const model = defineModel<WishlistColor>({ required: true });
</script>

<template>
    <div class="color-picker" role="group" aria-label="Цвет списка">
        <button
            v-for="color in WISHLIST_COLORS"
            :key="color.value"
            type="button"
            :class="[
                'color-swatch',
                `wishlist-color--${color.value}`,
                { 'is-active': model === color.value },
            ]"
            :title="color.label"
            :aria-label="color.label"
            :aria-pressed="model === color.value"
            @click="model = color.value"
        >
            <Check v-if="model === color.value" :size="14" />
        </button>
    </div>
</template>

<style scoped lang="scss">
@use '../../../scss/ui/wishlistColors.scss';

// Кружки растянуты на всю ширину формы: крайние стоят вровень с полями ввода.
// Без переноса строк: при переносе space-between раскидал бы вторую строку по краям
.color-picker {
    display: flex;
    justify-content: space-between;
    gap: 6px;
}

.color-swatch {
    display: grid;
    place-items: center;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    border: 1.5px solid rgba(139, 92, 246, 0.25);
    background: var(--wishlist-bg, #fff);
    color: var(--brand-violet, #8b5cf6);
    cursor: pointer;
    transition:
        transform 0.15s ease,
        box-shadow 0.15s ease;

    &:hover {
        transform: scale(1.08);
    }

    &:focus-visible {
        outline: 2px solid var(--brand-violet, #8b5cf6);
        outline-offset: 2px;
    }

    // Для white в палитре переменная не задаётся (у белых списков прежний фон),
    // поэтому без этого кружок унаследовал бы --wishlist-bg от окрашенной модалки
    &.wishlist-color--white {
        --wishlist-bg: #fff;
    }

    // Выбранный цвет: кольцо фирменного цвета с белым зазором
    &.is-active {
        border-color: transparent;
        box-shadow:
            0 0 0 2px #fff,
            0 0 0 4px var(--brand-violet, #8b5cf6);
    }
}
</style>
