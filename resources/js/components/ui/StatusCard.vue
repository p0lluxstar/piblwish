<script setup lang="ts">
import { RefreshCw } from '@lucide/vue';
import { type Component, computed, useId } from 'vue';

import { useAuthStore } from '@/stores/auth';

// Карточка вместо содержимого страницы, которое не удалось показать:
// страница 404 и общая страница списка, который не открылся.
// Последней кнопкой всегда идёт ссылка домой
const props = withDefaults(
    defineProps<{
        // Крупный код вместо значка: «404» на странице 404
        code?: string;
        icon?: Component;
        title: string;
        text: string;
        // Возможные причины списком под текстом
        reasons?: string[];
        // Мелкая подсказка под причинами
        hint?: string;
        // Кнопка «Попробовать снова» перед ссылкой домой; она становится основной
        retry?: boolean;
    }>(),
    {
        code: '',
        icon: undefined,
        reasons: () => [],
        hint: '',
        retry: false,
    },
);

const emit = defineEmits<{
    retry: [];
}>();

const auth = useAuthStore();
const titleId = useId();

// Вошедшего пользователя ведём к его карточкам, гостя — на главную
const homeLink = computed(() =>
    auth.isAuthenticated
        ? { to: '/dashboard', label: 'К моим карточкам' }
        : { to: '/', label: 'На главную' },
);
</script>

<template>
    <section class="status" :aria-labelledby="titleId">
        <div v-if="props.code" class="status-code" aria-hidden="true">
            {{ props.code }}
        </div>

        <div v-else-if="props.icon" class="status-icon" aria-hidden="true">
            <component :is="props.icon" :size="34" :stroke-width="2" />
        </div>

        <h1 :id="titleId" class="status-title">{{ props.title }}</h1>

        <p class="status-text">{{ props.text }}</p>

        <ul v-if="props.reasons.length" class="status-reasons">
            <li v-for="reason in props.reasons" :key="reason">
                {{ reason }}
            </li>
        </ul>

        <p v-if="props.hint" class="status-hint">{{ props.hint }}</p>

        <div class="status-actions">
            <button
                v-if="props.retry"
                type="button"
                class="status-btn status-btn--primary"
                @click="emit('retry')"
            >
                <RefreshCw :size="15" :stroke-width="2.4" />
                Попробовать снова
            </button>

            <RouterLink
                :to="homeLink.to"
                class="status-btn"
                :class="{ 'status-btn--primary': !props.retry }"
            >
                {{ homeLink.label }}
            </RouterLink>
        </div>
    </section>
</template>

<style scoped lang="scss">
.status {
    display: flex;
    flex-direction: column;
    align-items: center;
    max-width: 440px;
    margin: 40px auto 0;
    padding: 36px 28px 30px;
    text-align: center;
    background: linear-gradient(
        145deg,
        var(--surface-solid) 60%,
        var(--surface-blush)
    );
    border: 1px solid var(--surface-border);
    border-radius: 24px;
    box-shadow: var(--shadow-glow);
    animation: status-in 0.35s ease both;
}

.status-icon {
    display: flex;
    justify-content: center;
    align-items: center;
    width: 72px;
    height: 72px;
    margin-bottom: 18px;
    border-radius: 50%;
    background: var(--brand-gradient);
    color: #fff;
    box-shadow: var(--shadow-glow);
}

.status-code {
    margin: -6px 0 6px;
    font-size: 88px;
    font-weight: 900;
    line-height: 1;
    letter-spacing: -0.04em;
    background: var(--brand-gradient);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
}

.status-title {
    margin: 0 0 10px;
    font-size: 21px;
    font-weight: 800;
    color: var(--ink);
}

.status-text {
    margin: 0;
    font-size: 14px;
    line-height: 1.5;
    color: var(--ink-soft);
}

.status-reasons {
    margin: 10px 0 0;
    padding: 12px 16px 12px 32px;
    width: 100%;
    text-align: left;
    font-size: 14px;
    line-height: 1.55;
    color: var(--ink-soft);
    background: var(--tint);
    border-radius: 14px;

    li + li {
        margin-top: 4px;
    }

    li::marker {
        color: var(--brand-pink);
    }
}

.status-hint {
    margin: 14px 0 0;
    font-size: 13px;
    line-height: 1.5;
    color: var(--ink-muted);
}

.status-actions {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 10px;
    margin-top: 24px;
}

.status-btn {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 11px 20px;
    border: 1px solid var(--surface-border);
    border-radius: 999px;
    background: var(--surface-solid);
    color: var(--ink);
    font-family: inherit;
    font-size: 13px;
    font-weight: 700;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.2s ease;

    &:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-glow);
    }

    &--primary {
        border-color: transparent;
        background: var(--brand-gradient);
        color: #fff;
        box-shadow: var(--shadow-glow);

        &:hover {
            box-shadow: var(--shadow-glow-lg);
        }
    }
}

@keyframes status-in {
    from {
        opacity: 0;
        transform: translateY(8px);
    }
}

@media (prefers-reduced-motion: reduce) {
    .status {
        animation: none;
    }
}

@media (max-width: 480px) {
    .status {
        margin-top: 16px;
        padding: 28px 18px 24px;
    }

    .status-code {
        font-size: 72px;
    }
}
</style>
