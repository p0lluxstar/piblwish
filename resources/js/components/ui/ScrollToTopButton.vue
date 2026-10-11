<script setup lang="ts">
import { ArrowUp } from '@lucide/vue';
import { onBeforeUnmount, onMounted, ref } from 'vue';

// Прокрутка в пикселях, после которой появляется кнопка
const SHOW_AFTER = 300;

const isVisible = ref(false);

const updateVisibility = (): void => {
    isVisible.value = window.scrollY > SHOW_AFTER;
};

const scrollToTop = (): void => {
    // При системной настройке «уменьшить движение» страница перематывается без анимации
    const reduceMotion = window.matchMedia(
        '(prefers-reduced-motion: reduce)',
    ).matches;

    window.scrollTo({ top: 0, behavior: reduceMotion ? 'auto' : 'smooth' });
};

onMounted(() => {
    updateVisibility();
    window.addEventListener('scroll', updateVisibility, { passive: true });
});

onBeforeUnmount(() => {
    window.removeEventListener('scroll', updateVisibility);
});
</script>

<template>
    <Transition name="scroll-top">
        <button
            v-if="isVisible"
            class="scroll-top-btn"
            type="button"
            aria-label="Наверх"
            title="Наверх"
            @click="scrollToTop"
        >
            <ArrowUp :size="18" :stroke-width="2.4" />
        </button>
    </Transition>
</template>

<style scoped>
.scroll-top-btn {
    position: fixed;
    right: 28px;
    bottom: 28px;
    /* Выше шапки (10), но ниже модальных окон (1000) */
    z-index: 20;
    display: grid;
    place-items: center;
    width: 44px;
    height: 44px;
    border: none;
    border-radius: 50%;
    background: var(--brand-gradient);
    color: #fff;
    box-shadow: var(--shadow-glow);
    cursor: pointer;
    transition:
        transform 0.2s ease,
        box-shadow 0.2s ease;
}

.scroll-top-btn:hover {
    transform: translateY(-2px) scale(1.05);
    box-shadow: var(--shadow-glow-lg);
}

.scroll-top-enter-active,
.scroll-top-leave-active {
    transition:
        opacity 0.2s ease,
        transform 0.2s ease;
}

.scroll-top-enter-from,
.scroll-top-leave-to {
    opacity: 0;
    transform: translateY(10px);
}

@media (max-width: 599px) {
    .scroll-top-btn {
        right: 16px;
        bottom: 16px;
        width: 40px;
        height: 40px;
    }
}
</style>
