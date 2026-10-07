<script setup lang="ts">
import { Check } from '@lucide/vue';
import { computed } from 'vue';

import FormErrorMessage from '@/components/ui/FormErrorMessage.vue';
import { useUpdateBackground } from '@/composables/useAuth';
import {
    APP_BACKGROUNDS,
    DEFAULT_APP_BACKGROUND,
} from '@/constants/appBackgrounds';
import { useAuthStore } from '@/stores/auth';
import type { AppBackground } from '@/types/user';

const auth = useAuthStore();
const { mutate: updateBackground, errorMessage } = useUpdateBackground();

const current = computed(
    (): AppBackground => auth.user?.background ?? DEFAULT_APP_BACKGROUND,
);

// Фон сохраняется сразу при выборе, без отдельной кнопки
const select = (background: AppBackground): void => {
    if (background === current.value) return;

    updateBackground(background);
};
</script>

<template>
    <div class="background-picker">
        <div class="swatches" role="group" aria-label="Фон приложения">
            <button
                v-for="background in APP_BACKGROUNDS"
                :key="background.value"
                type="button"
                :class="[
                    'swatch',
                    `app-bg--${background.value}`,
                    { 'is-active': current === background.value },
                ]"
                :title="background.label"
                :aria-label="background.label"
                :aria-pressed="current === background.value"
                @click="select(background.value)"
            >
                <Check v-if="current === background.value" :size="14" />
            </button>
        </div>

        <FormErrorMessage
            class="error-message"
            :show="!!errorMessage"
            :message="errorMessage ?? undefined"
        />
    </div>
</template>

<style scoped lang="scss">
@use '../../../scss/ui/appBackgrounds.scss';

.swatches {
    display: flex;
    justify-content: space-between;
    gap: 8px;
}

// Превью фона: градиент страницы с двумя пятнами, как на самой странице
.swatch {
    display: grid;
    place-items: center;
    flex: 1;
    height: 40px;
    border-radius: 12px;
    border: 1.5px solid rgba(139, 92, 246, 0.2);
    background:
        radial-gradient(
            circle at 20% 25%,
            var(--app-preview-glow-1, var(--app-glow-1)),
            transparent 55%
        ),
        radial-gradient(
            circle at 85% 80%,
            var(--app-preview-glow-2, var(--app-glow-2)),
            transparent 55%
        ),
        var(--app-bg);
    // У тёмного фона галочка светлая: класс фона задаёт --app-ink
    color: var(--app-ink, var(--ink, #241533));
    cursor: pointer;
    transition:
        transform 0.15s ease,
        box-shadow 0.15s ease;

    &:hover {
        transform: scale(1.05);
    }

    &:focus-visible {
        outline: 2px solid var(--brand-violet, #8b5cf6);
        outline-offset: 2px;
    }

    // Выбранный фон: кольцо фирменного цвета с белым зазором
    &.is-active {
        border-color: transparent;
        box-shadow:
            0 0 0 2px #fff,
            0 0 0 4px var(--brand-violet, #8b5cf6);
    }
}

.error-message {
    margin: 10px 0 0;
    font-size: 13px;
}
</style>
