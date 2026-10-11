<script setup lang="ts">
import { X } from '@lucide/vue';
import { onBeforeUnmount, onMounted } from 'vue';

const props = withDefaults(
    defineProps<{
        message: string;
        // Подпись кнопки действия («Отменить»); без неё кнопки нет
        actionLabel?: string | null;
        tone?: 'default' | 'error';
        // Через сколько миллисекунд уведомление закрывается само
        duration?: number;
    }>(),
    { actionLabel: null, tone: 'default', duration: 6000 },
);

const emit = defineEmits<{
    action: [];
    close: [];
}>();

// Новое уведомление показывается заново созданным компонентом (key),
// поэтому таймер запускается один раз
let closeTimer: number | null = null;

onMounted(() => {
    closeTimer = window.setTimeout(() => emit('close'), props.duration);
});

onBeforeUnmount(() => {
    if (closeTimer) window.clearTimeout(closeTimer);
});

const runAction = (): void => {
    emit('action');
    emit('close');
};
</script>

<template>
    <div
        :class="['toast', { 'toast--error': tone === 'error' }]"
        :role="tone === 'error' ? 'alert' : 'status'"
    >
        <span class="toast-message">{{ message }}</span>
        <button
            v-if="actionLabel"
            type="button"
            class="toast-action"
            @click="runAction"
        >
            {{ actionLabel }}
        </button>
        <button
            type="button"
            class="toast-close"
            aria-label="Закрыть уведомление"
            title="Закрыть"
            @click="emit('close')"
        >
            <X :size="14" />
        </button>
    </div>
</template>

<style scoped lang="scss">
// Внизу по центру, выше окон (z-index 1000): уведомление может появиться
// после действия в окне просмотра карточки
.toast {
    position: fixed;
    left: 50%;
    bottom: 28px;
    z-index: 1100;
    display: flex;
    align-items: center;
    gap: 12px;
    width: max-content;
    max-width: calc(100vw - 32px);
    padding: 10px 10px 10px 18px;
    border-radius: 16px;
    background: var(--ink);
    color: #fff;
    font-size: 13px;
    font-weight: 500;
    line-height: 1.4;
    box-shadow: 0 14px 34px -12px rgba(36, 21, 51, 0.5);
    transform: translateX(-50%);
    animation: toastIn 0.2s ease-out;
}

.toast--error {
    background: #b91c1c;
}

.toast-action {
    flex-shrink: 0;
    padding: 4px 10px;
    border-radius: 10px;
    color: #c4b5fd;
    font-size: 13px;
    font-weight: 700;
    font-family: inherit;
    cursor: pointer;
    transition: background 0.15s ease;

    &:hover {
        background: rgba(255, 255, 255, 0.12);
    }
}

.toast-close {
    flex-shrink: 0;
    display: grid;
    place-items: center;
    width: 24px;
    height: 24px;
    border-radius: 8px;
    color: rgba(255, 255, 255, 0.6);
    cursor: pointer;
    transition: all 0.15s ease;

    &:hover {
        color: #fff;
        background: rgba(255, 255, 255, 0.12);
    }
}

@keyframes toastIn {
    from {
        opacity: 0;
        transform: translate(-50%, 8px);
    }
}

@media (max-width: 599px) {
    .toast {
        bottom: 16px;
    }
}
</style>
