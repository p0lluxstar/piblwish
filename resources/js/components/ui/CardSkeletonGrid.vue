<script setup lang="ts">
// Заглушки карточек на время первой загрузки: сетка и высота совпадают
// с настоящими карточками, поэтому после загрузки страница не сдвигается
withDefaults(
    defineProps<{
        count?: number;
        // Высота карточки в пикселях: у карточек дашборда она фиксированная
        height?: number;
    }>(),
    {
        count: 8,
        height: 280,
    },
);
</script>

<template>
    <div class="skeleton-grid" role="status" aria-busy="true">
        <span class="sr-only">Загрузка…</span>
        <div
            v-for="index in count"
            :key="index"
            class="skeleton-card"
            :style="{ height: `${height}px` }"
            aria-hidden="true"
        >
            <div class="skeleton-row">
                <span class="skeleton-line skeleton-line--badge" />
                <span class="skeleton-line skeleton-line--dot" />
            </div>
            <span class="skeleton-line skeleton-line--title" />
            <div class="skeleton-items">
                <span class="skeleton-line" />
                <span class="skeleton-line skeleton-line--short" />
                <span class="skeleton-line skeleton-line--mid" />
            </div>
            <span class="skeleton-line skeleton-line--footer" />
        </div>
    </div>
</template>

<style scoped lang="scss">
.skeleton-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    gap: 20px;
}

.skeleton-card {
    display: flex;
    flex-direction: column;
    gap: 14px;
    padding: 20px;
    border: 1px solid var(--surface-border);
    border-radius: var(--radius-lg);
    background: var(--surface);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    overflow: hidden;
}

.skeleton-row {
    display: flex;
    justify-content: space-between;
}

.skeleton-items {
    display: flex;
    flex: 1;
    flex-direction: column;
    gap: 10px;
    min-height: 0;
    overflow: hidden;
}

// Блик проходит по всем полоскам одновременно: фон шире полоски и сдвигается
.skeleton-line {
    display: block;
    flex-shrink: 0;
    width: 100%;
    height: 12px;
    border-radius: 999px;
    background: linear-gradient(
        90deg,
        rgba(148, 163, 184, 0.16) 25%,
        rgba(148, 163, 184, 0.32) 50%,
        rgba(148, 163, 184, 0.16) 75%
    );
    background-size: 200% 100%;
    animation: skeleton-shimmer 1.4s ease-in-out infinite;
}

.skeleton-line--badge {
    width: 72px;
    height: 20px;
}

.skeleton-line--dot {
    width: 20px;
    height: 20px;
}

.skeleton-line--title {
    width: 70%;
    height: 18px;
}

.skeleton-line--short {
    width: 55%;
}

.skeleton-line--mid {
    width: 80%;
}

.skeleton-line--footer {
    width: 40%;
    height: 10px;
}

@keyframes skeleton-shimmer {
    from {
        background-position: 100% 0;
    }
    to {
        background-position: -100% 0;
    }
}

@media (prefers-reduced-motion: reduce) {
    .skeleton-line {
        animation: none;
    }
}
</style>
