<script setup lang="ts">
import { Heart, Zap } from '@lucide/vue';
import { computed } from 'vue';

import { ITEM_PRIORITIES, itemPriorityLabels } from '../../lib/itemPriority';
import type { WishlistItemPriority } from '../../types/wishlist';

// Приоритет позиции в карточке и на общей странице: закрашено столько сердец,
// каков приоритет (у дела — молний, они означают важность); подпись выводится
// во всплывающей подсказке
const props = defineProps<{
    priority: WishlistItemPriority;
    // Позиция списка дел: молнии вместо сердец
    todo?: boolean;
    // Подарок уже выбран или дело выполнено: значки приглушены в тон серому
    // тексту позиции
    muted?: boolean;
}>();

const label = computed(() => itemPriorityLabels(props.todo)[props.priority]);
</script>

<template>
    <span
        :class="[
            'priority',
            { 'priority--todo': todo, 'priority--muted': muted },
        ]"
        role="img"
        :aria-label="label"
        :title="label"
    >
        <component
            :is="todo ? Zap : Heart"
            v-for="value in ITEM_PRIORITIES"
            :key="value"
            :size="11"
            :class="[
                'priority-icon',
                { 'priority-icon--on': value <= priority },
            ]"
            aria-hidden="true"
        />
    </span>
</template>

<style scoped lang="scss">
.priority {
    display: inline-flex;
    flex-shrink: 0;
    gap: 1px;
    color: var(--brand-pink);
}

.priority--todo {
    color: #f59e0b;
}

.priority-icon {
    opacity: 0.3;
}

.priority-icon--on {
    opacity: 1;
    fill: currentColor;
}

.priority--muted {
    color: var(--ink-muted);
}
</style>
