<script setup lang="ts">
import { Moon, Sun } from '@lucide/vue';
import { computed } from 'vue';

import { useTheme } from '@/composables/useTheme';

// Быстрое переключение темы для гостя: в шапке и на лендинге.
// Вошедший пользователь выбирает тему в окне настроек (ThemePicker)
const { theme, toggleTheme } = useTheme();

const label = computed((): string =>
    theme.value === 'dark' ? 'Светлая тема' : 'Тёмная тема',
);
</script>

<template>
    <button
        type="button"
        class="theme-toggle"
        :aria-label="label"
        :title="label"
        @click="toggleTheme"
    >
        <Sun v-if="theme === 'dark'" :size="16" />
        <Moon v-else :size="16" />
    </button>
</template>

<style scoped>
.theme-toggle {
    display: grid;
    place-items: center;
    flex-shrink: 0;
    width: 36px;
    height: 36px;
    padding: 0;
    border: none;
    border-radius: 50%;
    background: var(--tint);
    color: var(--brand-violet);
    cursor: pointer;
    transition: all 0.2s ease;
}

.theme-toggle:hover {
    background: var(--tint-strong);
    transform: translateY(-1px);
}

.theme-toggle:focus-visible {
    outline: 2px solid var(--brand-violet);
    outline-offset: 2px;
}
</style>
