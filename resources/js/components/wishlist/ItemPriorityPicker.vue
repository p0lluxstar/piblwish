<script setup lang="ts">
import { Heart } from '@lucide/vue';
import { computed, ref } from 'vue';

import { ITEM_PRIORITIES, ITEM_PRIORITY_LABELS } from '../../lib/itemPriority';
import type { WishlistItemPriority } from '../../types/wishlist';

// Выбор приоритета позиции в модалках: число закрашенных сердец равно приоритету.
// Повторное нажатие на выбранное значение снимает приоритет
const model = defineModel<WishlistItemPriority | null | undefined>();

defineProps<{
    // Подарок уже выбран гостем: сердца и подпись серые, как в карточке
    muted?: boolean;
}>();

// Значение под курсором: сердца подсвечиваются до него, пока выбор не сделан
const hovered = ref<WishlistItemPriority | null>(null);

const shown = computed(() => hovered.value ?? model.value ?? null);

const caption = computed(() =>
    shown.value ? ITEM_PRIORITY_LABELS[shown.value] : 'Приоритет не указан',
);

const select = (value: WishlistItemPriority): void => {
    model.value = model.value === value ? null : value;
};
</script>

<template>
    <div
        :class="['priority-picker', { 'priority-picker--muted': muted }]"
        @mouseleave="hovered = null"
    >
        <div class="priority-hearts" role="group" aria-label="Приоритет">
            <button
                v-for="value in ITEM_PRIORITIES"
                :key="value"
                type="button"
                :class="[
                    'priority-heart',
                    { 'priority-heart--on': shown !== null && value <= shown },
                ]"
                :aria-label="ITEM_PRIORITY_LABELS[value]"
                :aria-pressed="model === value"
                :title="
                    model === value
                        ? 'Снять приоритет'
                        : ITEM_PRIORITY_LABELS[value]
                "
                @mouseenter="hovered = value"
                @click="select(value)"
            >
                <Heart :size="12" />
            </button>
        </div>

        <!-- Подпись справа от сердец -->
        <span
            :class="['priority-caption', { 'priority-caption--empty': !shown }]"
        >
            {{ caption }}
        </span>
    </div>
</template>

<style scoped lang="scss">
.priority-picker {
    display: flex;
    align-items: center;
    gap: 4px;
}

.priority-hearts {
    display: flex;
    gap: 0;
}

.priority-heart {
    display: grid;
    place-items: center;
    width: 17px;
    height: 20px;
    padding: 0;
    border: none;
    border-radius: 7px;
    background: transparent;
    color: rgba(139, 92, 246, 0.35);
    cursor: pointer;
    transition: all 0.15s ease;

    &:hover {
        background: rgba(236, 72, 153, 0.08);
    }

    &:focus-visible {
        outline: 2px solid rgba(236, 72, 153, 0.5);
        outline-offset: 1px;
    }
}

.priority-heart--on {
    color: #ec4899;

    :deep(svg) {
        fill: currentColor;
    }
}

.priority-caption {
    font-size: 9px;
    line-height: 1.2;
    color: #ec4899;
    font-weight: 600;
}

.priority-caption--empty {
    color: #b3a3bd;
    font-weight: 400;
}

.priority-picker--muted {
    .priority-heart {
        color: rgba(148, 163, 184, 0.45);

        &:hover {
            background: rgba(148, 163, 184, 0.12);
        }
    }

    .priority-heart--on,
    .priority-caption {
        color: #94a3b8;
    }
}
</style>
