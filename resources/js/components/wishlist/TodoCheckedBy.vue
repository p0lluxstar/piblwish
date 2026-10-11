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
    // на своей карточке): вместо имени «Вы», выделенное цветом; если гость
    // вводил имя, оно выводится рядом в скобках
    isOwn?: boolean;
    // Имя в одну строку с многоточием (карточка в дашборде); полное имя —
    // в подсказке. Не задано — длинное имя переносится
    truncate?: boolean;
}>();

const name = computed(() =>
    props.isOwn ? 'Вы' : checkedByName(props.checkedBy, props.ownerName),
);

// Имя, под которым гость отметил дело; у отметок владельца его нет
const ownGuestName = computed(() =>
    props.isOwn ? (props.checkedBy?.name ?? null) : null,
);

const fullLabel = computed(() => {
    if (!props.isOwn) return `Отметил(а): ${name.value}`;

    const guestName = ownGuestName.value;

    return guestName ? `Отметили вы (как ${guestName})` : 'Отметили вы';
});
</script>

<template>
    <!-- Значок человека и имя справа от дела; полная фраза — в подсказке
         и для экранных дикторов -->
    <span
        v-if="name"
        class="todo-checked-by"
        :class="{
            'todo-checked-by--own': isOwn,
            'todo-checked-by--truncate': truncate,
        }"
        :title="fullLabel"
        :aria-label="fullLabel"
    >
        <UserRound :size="11" class="todo-checked-by-icon" aria-hidden="true" />
        <span class="todo-checked-by-name" aria-hidden="true">
            {{ name }}
            <span v-if="ownGuestName" class="todo-checked-by-guest-name">
                ({{ ownGuestName }})
            </span>
        </span>
    </span>
    <span v-else-if="emptyLabel" class="todo-checked-by">
        {{ emptyLabel }}
    </span>
</template>

<style scoped lang="scss">
// Колонка справа от названия дела: не шире 45% строки, длинное имя
// переносится внутри неё, а не сужает название. Значок и имя идут одним
// строчным потоком, поэтому при переносе значок остаётся перед первым словом,
// а не у левого края колонки
.todo-checked-by {
    display: inline-block;
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

// Введённое гостем имя рядом с «Вы» — обычным начертанием, чтобы «Вы»
// оставалось главным
.todo-checked-by-guest-name {
    font-weight: 400;
}

// Значок перед именем, по середине строки текста. Tailwind (preflight) делает
// svg блочным, поэтому строчный вид задан явно
.todo-checked-by-icon {
    display: inline-block;
    margin-right: 3px;
    vertical-align: -1px;
}

// Имя не переносится, а обрезается многоточием по ширине колонки:
// для этого значок и имя — отдельные элементы flex-строки
.todo-checked-by--truncate {
    display: inline-flex;
    align-items: center;
    gap: 3px;

    // Промежуток до имени здесь даёт gap
    .todo-checked-by-icon {
        flex-shrink: 0;
        margin-right: 0;
    }

    .todo-checked-by-name {
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
}
</style>
