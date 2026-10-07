<script setup lang="ts">
import { computed } from 'vue';

import Footer from '@/components/dashboard/Footer.vue';
import Header from '@/components/dashboard/Header.vue';
import Main from '@/components/dashboard/Main.vue';
import ScrollToTopButton from '@/components/ui/ScrollToTopButton.vue';
import { DEFAULT_APP_BACKGROUND } from '@/constants/appBackgrounds';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();

// Фон, выбранный в настройках аккаунта; у гостя — фон по умолчанию
const backgroundClass = computed(
    (): string => `app-bg--${auth.user?.background ?? DEFAULT_APP_BACKGROUND}`,
);
</script>

<template>
    <div class="dashboard" :class="backgroundClass">
        <div class="dashboard-glow dashboard-glow--first"></div>
        <div class="dashboard-glow dashboard-glow--second"></div>
        <div class="dashboard-glow dashboard-glow--third"></div>

        <Header />
        <Main />
        <Footer />
        <ScrollToTopButton />
    </div>
</template>

<style scoped lang="scss">
@use '../../scss/ui/appBackgrounds.scss';

.dashboard {
    position: relative;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    background: var(--app-bg);
    overflow-x: hidden;
    isolation: isolate;
}

.dashboard-glow {
    position: fixed;
    z-index: -1;
    border-radius: 50%;
    filter: blur(90px);
    opacity: var(--app-glow-opacity, 0.35);
    pointer-events: none;
    transition: background-color 0.4s ease;
}

.dashboard-glow--first {
    top: -120px;
    left: -100px;
    width: 420px;
    height: 420px;
    background: var(--app-glow-1);
}

.dashboard-glow--second {
    top: 20%;
    right: -140px;
    width: 380px;
    height: 380px;
    background: var(--app-glow-2);
}

.dashboard-glow--third {
    bottom: -140px;
    left: 30%;
    width: 360px;
    height: 360px;
    background: var(--app-glow-3);
    // Нижнее пятно чуть прозрачнее двух других
    opacity: calc(var(--app-glow-opacity, 0.35) - 0.05);
}
</style>
