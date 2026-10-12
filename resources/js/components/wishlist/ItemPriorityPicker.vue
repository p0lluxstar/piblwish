<script setup lang="ts">
import { Heart, Zap } from '@lucide/vue';
import { computed, ref } from 'vue';

import { ITEM_PRIORITIES, itemPriorityLabels } from '../../lib/itemPriority';
import type { WishlistItemPriority } from '../../types/wishlist';

// Выбор приоритета позиции в модалках: число закрашенных сердец равно приоритету.
// У дела вместо сердец молнии, и значение означает важность.
// Повторное нажатие на выбранное значение снимает приоритет
const model = defineModel<WishlistItemPriority | null | undefined>();

const props = defineProps<{
    // Позиция списка дел: молнии и подписи важности
    todo?: boolean;
    // Подарок уже выбран гостем: сердца и подпись серые, как в карточке
    muted?: boolean;
}>();

// Значение под курсором: значки подсвечиваются до него, пока выбор не сделан
const hovered = ref<WishlistItemPriority | null>(null);

const shown = computed(() => hovered.value ?? model.value ?? null);

const labels = computed(() => itemPriorityLabels(props.todo));

const name = computed(() => (props.todo ? 'Важность' : 'Приоритет'));

const caption = computed(() =>
    shown.value
        ? labels.value[shown.value]
        : props.todo
          ? 'Важность не указана'
          : 'Приоритет не указан',
);

const select = (value: WishlistItemPriority): void => {
    model.value = model.value === value ? null : value;
};
</script>

<template>
    <div
        :class="[
            'priority-picker',
            { 'priority-picker--todo': todo, 'priority-picker--muted': muted },
        ]"
        @mouseleave="hovered = null"
    >
        <div class="priority-icons" role="group" :aria-label="name">
            <button
                v-for="value in ITEM_PRIORITIES"
                :key="value"
                type="button"
                :class="[
                    'priority-icon',
                    { 'priority-icon--on': shown !== null && value <= shown },
                ]"
                :aria-label="labels[value]"
                :aria-pressed="model === value"
                :title="
                    model === value
                        ? `Снять ${name.toLowerCase()}`
                        : labels[value]
                "
                @mouseenter="hovered = value"
                @click="select(value)"
            >
                <component :is="todo ? Zap : Heart" :size="12" />
            </button>
        </div>

        <!-- Подпись справа от значков -->
        <span
            :class="['priority-caption', { 'priority-caption--empty': !shown }]"
        >
            {{ caption }}
        </span>
    </div>
</template>

<style scoped lang="scss">
.priority-picker {
    // Цвета значков: у подарка розовые сердца, у дела янтарные молнии
    --priority-off: rgba(139, 92, 246, 0.35);
    --priority-on: var(--brand-pink);
    --priority-hover: rgba(236, 72, 153, 0.08);
    --priority-focus: rgba(236, 72, 153, 0.5);
    --priority-caption: var(--brand-pink);

    display: flex;
    align-items: center;
    gap: 4px;
}

.priority-picker--todo {
    --priority-off: rgba(245, 158, 11, 0.4);
    --priority-on: #f59e0b;
    --priority-hover: rgba(245, 158, 11, 0.1);
    --priority-focus: rgba(245, 158, 11, 0.5);
    --priority-caption: #d97706;
}

.priority-icons {
    display: flex;
    gap: 0;
}

.priority-icon {
    display: grid;
    place-items: center;
    width: 17px;
    height: 20px;
    padding: 0;
    border: none;
    border-radius: 7px;
    background: transparent;
    color: var(--priority-off);
    cursor: pointer;
    transition: all 0.15s ease;

    &:hover {
        background: var(--priority-hover);
    }

    &:focus-visible {
        outline: 2px solid var(--priority-focus);
        outline-offset: 1px;
    }
}

.priority-icon--on {
    color: var(--priority-on);

    :deep(svg) {
        fill: currentColor;
    }
}

.priority-caption {
    font-size: 9px;
    line-height: 1.2;
    color: var(--priority-caption);
    font-weight: 600;
}

.priority-caption--empty {
    color: var(--ink-faint);
    font-weight: 400;
}

.priority-picker--muted {
    .priority-icon {
        color: rgba(148, 163, 184, 0.45);

        &:hover {
            background: rgba(148, 163, 184, 0.12);
        }
    }

    .priority-icon--on,
    .priority-caption {
        color: var(--ink-muted);
    }
}
</style>
