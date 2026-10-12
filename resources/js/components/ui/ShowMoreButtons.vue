<script setup lang="ts">
// Кнопки под сеткой карточек (composables/useCardPaging.ts): следующая порция
// и все оставшиеся карточки
defineProps<{
    hiddenCount: number;
    nextCount: number;
}>();

defineEmits<{
    more: [];
    all: [];
}>();
</script>

<template>
    <div v-if="hiddenCount > 0" class="more">
        <button class="more-btn" @click="$emit('more')">
            Показать ещё
            <span class="more-count">{{ nextCount }}</span>
        </button>
        <!-- Когда осталась одна порция, кнопка повторяла бы «Показать ещё» -->
        <button
            v-if="hiddenCount > nextCount"
            class="more-btn"
            @click="$emit('all')"
        >
            Показать все
            <span class="more-count">{{ hiddenCount }}</span>
        </button>
    </div>
</template>

<style scoped lang="scss">
.more {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 10px;
    margin-top: 28px;
}

.more-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    background: var(--surface-solid);
    color: var(--brand-violet);
    border: 1.5px solid var(--surface-border);
    padding: 9px 22px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    font-family: inherit;
    line-height: 1.5;
    cursor: pointer;
    transition: all 0.2s ease;

    &:hover {
        border-color: var(--brand-violet);
        box-shadow: var(--shadow-glow);
    }
}

.more-count {
    color: var(--ink-soft);
    font-weight: 500;
}
</style>
