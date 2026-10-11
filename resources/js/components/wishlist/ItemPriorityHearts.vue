<script setup lang="ts">
import { Heart } from '@lucide/vue';

import { ITEM_PRIORITIES, ITEM_PRIORITY_LABELS } from '../../lib/itemPriority';
import type { WishlistItemPriority } from '../../types/wishlist';

// Приоритет позиции в карточке и на общей странице: закрашено столько сердец,
// каков приоритет; подпись выводится во всплывающей подсказке
defineProps<{
    priority: WishlistItemPriority;
    // Подарок уже выбран: сердца приглушены в тон серому тексту позиции
    muted?: boolean;
}>();
</script>

<template>
    <span
        :class="['priority', { 'priority--muted': muted }]"
        role="img"
        :aria-label="ITEM_PRIORITY_LABELS[priority]"
        :title="ITEM_PRIORITY_LABELS[priority]"
    >
        <Heart
            v-for="value in ITEM_PRIORITIES"
            :key="value"
            :size="11"
            :class="[
                'priority-heart',
                { 'priority-heart--on': value <= priority },
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
    color: #ec4899;
}

.priority-heart {
    opacity: 0.3;
}

.priority-heart--on {
    opacity: 1;
    fill: currentColor;
}

.priority--muted {
    color: #94a3b8;
}
</style>
