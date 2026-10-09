<script setup lang="ts">
import { UserRound } from '@lucide/vue';
import { computed } from 'vue';

import { checkedByName } from '@/lib/todoCheckedBy';
import type { WishlistItemCheckedBy } from '@/types/wishlist';

const props = defineProps<{
    checkedBy: WishlistItemCheckedBy | null | undefined;
    // Имя владельца для его собственных отметок; null — они не подписываются
    ownerName: string | null;
    // Текст вместо имени, если подписывать нечего (например, «Не выполнено»):
    // выводится без значка; не задан — подпись не выводится вовсе
    emptyLabel?: string;
    // Дело отметил тот, кто смотрит список (гость на общей странице или владелец
    // на своей карточке): вместо имени «Вы», выделенное цветом
    isOwn?: boolean;
}>();

const name = computed(() =>
    props.isOwn ? 'Вы' : checkedByName(props.checkedBy, props.ownerName),
);

const fullLabel = computed(() => {
    if (!props.isOwn) return `Отметил(а): ${name.value}`;

    const guestName = props.checkedBy?.name;

    return guestName ? `Отметили вы (как ${guestName})` : 'Отметили вы';
});
</script>

<template>
    <!-- Значок человека и имя справа от дела; полная фраза — в подсказке
         и для экранных дикторов -->
    <span
        v-if="name"
        class="todo-checked-by"
        :class="{ 'todo-checked-by--own': isOwn }"
        :title="fullLabel"
        :aria-label="fullLabel"
    >
        <UserRound :size="11" class="todo-checked-by-icon" aria-hidden="true" />
        <span aria-hidden="true">{{ name }}</span>
    </span>
    <span v-else-if="emptyLabel" class="todo-checked-by">
        {{ emptyLabel }}
    </span>
</template>

<style scoped lang="scss">
// Колонка справа от названия дела: не шире 45% строки, длинное имя
// переносится внутри неё, а не сужает название
.todo-checked-by {
    display: inline-flex;
    align-items: baseline;
    gap: 3px;
    flex-shrink: 0;
    max-width: 45%;
    font-size: 11px;
    font-style: italic;
    line-height: 1.3;
    text-align: right;
    color: #94a3b8;
    overflow-wrap: anywhere;
}

// Свои отметки гостя выделяются, чтобы их было видно среди чужих
.todo-checked-by--own {
    font-style: normal;
    font-weight: 600;
    color: #059669;
}

// Значок выровнен по первой строке имени
.todo-checked-by-icon {
    flex-shrink: 0;
    align-self: center;
}
</style>
