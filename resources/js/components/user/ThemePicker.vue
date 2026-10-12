<script setup lang="ts">
import { Monitor, Moon, Sun } from '@lucide/vue';
import type { Component } from 'vue';

import {
    THEME_OPTIONS,
    type ThemePreference,
    useTheme,
} from '@/composables/useTheme';

// Выбор темы в окне настроек. Тема хранится в браузере, поэтому
// на другом устройстве выбирается заново
const { preference, setPreference } = useTheme();

const ICONS: Record<ThemePreference, Component> = {
    light: Sun,
    dark: Moon,
    system: Monitor,
};
</script>

<template>
    <div class="theme-picker" role="group" aria-label="Тема оформления">
        <button
            v-for="option in THEME_OPTIONS"
            :key="option.value"
            type="button"
            :class="[
                'theme-option',
                { 'is-active': preference === option.value },
            ]"
            :aria-pressed="preference === option.value"
            @click="setPreference(option.value)"
        >
            <component :is="ICONS[option.value]" :size="14" />
            {{ option.label }}
        </button>
    </div>
</template>

<style scoped lang="scss">
.theme-picker {
    display: flex;
    gap: 4px;
    margin-bottom: 14px;
    padding: 4px;
    border-radius: 14px;
    background: var(--tint);
}

.theme-option {
    display: flex;
    flex: 1;
    align-items: center;
    justify-content: center;
    gap: 5px;
    padding: 7px 4px;
    border: none;
    border-radius: 10px;
    background: transparent;
    color: var(--ink-soft);
    font-family: inherit;
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
    cursor: pointer;
    transition: all 0.18s ease;

    &:hover {
        color: var(--brand-violet);
    }

    &:focus-visible {
        outline: 2px solid var(--brand-violet);
        outline-offset: -2px;
    }

    &.is-active {
        background: var(--surface-solid);
        color: var(--brand-violet);
        box-shadow: var(--shadow-soft);
    }
}
</style>
