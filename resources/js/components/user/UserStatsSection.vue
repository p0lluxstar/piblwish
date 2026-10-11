<script setup lang="ts">
import { CreditCardCheck, Gift, ListChecks, StickyNote } from '@lucide/vue';
import { type Component, computed } from 'vue';

import { useAuthStore } from '@/stores/auth';
import type { WishlistType } from '@/types/wishlist';

// Статистика в окне настроек: сколько карточек каждого типа пользователь
// создал за всё время. Удалённые карточки и дубликаты тоже учитываются
const auth = useAuthStore();

// Подписи как у фильтра по типу над карточками
const TYPES: { type: WishlistType; label: string; icon: Component }[] = [
    { type: 'gift', label: 'Желания', icon: Gift },
    { type: 'todo', label: 'Дела', icon: ListChecks },
    { type: 'note', label: 'Заметки', icon: StickyNote },
    { type: 'fund', label: 'Сборы', icon: CreditCardCheck },
];

const items = computed(() =>
    TYPES.map((item) => ({
        ...item,
        count: auth.user?.stats?.[item.type] ?? 0,
    })),
);

const total = computed(() =>
    items.value.reduce((sum, item) => sum + item.count, 0),
);
</script>

<template>
    <section class="stats-section">
        <h3 class="section-title">Статистика</h3>

        <p class="stats-hint">
            Создано карточек за всё время:
            <strong>{{ total }}</strong>
        </p>

        <ul class="stats-grid">
            <li v-for="item in items" :key="item.type" class="stats-item">
                <component
                    :is="item.icon"
                    :class="['stats-icon', `stats-icon--${item.type}`]"
                    :size="16"
                />
                <span class="stats-count">{{ item.count }}</span>
                <span class="stats-label">{{ item.label }}</span>
            </li>
        </ul>
    </section>
</template>

<style scoped lang="scss">
@use '../../../scss/ui/accountSection.scss';

.stats-section {
    margin-bottom: 20px;
    padding-bottom: 18px;
    border-bottom: 1px dashed rgba(139, 92, 246, 0.2);
}

.stats-hint {
    margin: 0 0 10px;
    font-size: 13px;
    color: var(--ink, #241533);

    strong {
        font-weight: 700;
    }
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 6px;
    margin: 0;
    padding: 0;
    list-style: none;
}

.stats-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 2px;
    min-width: 0;
    padding: 8px 4px;
    border: 1px solid rgba(139, 92, 246, 0.18);
    border-radius: 12px;
    background: #fff;
}

// Цвета как у меток типа на карточках дашборда (.card-type в WishlistCard)
.stats-icon--gift {
    color: var(--brand-violet, #8b5cf6);
}

.stats-icon--todo {
    color: #059669;
}

.stats-icon--note {
    color: #b45309;
}

.stats-icon--fund {
    color: #be185d;
}

.stats-count {
    font-size: 16px;
    font-weight: 700;
    color: var(--ink, #241533);
}

.stats-label {
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 11px;
    color: var(--ink-soft, #6b5878);
}
</style>
