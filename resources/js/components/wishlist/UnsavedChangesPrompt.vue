<script setup lang="ts">
import { X } from '@lucide/vue';
import { onMounted, useTemplateRef } from 'vue';

import LoaderButtonSpinner from '../ui/LoaderButtonSpinner.vue';

const props = withDefaults(
    defineProps<{
        // Надпись как у кнопки отправки формы: «Сохранить» или «Создать»
        saveLabel?: string;
        canSave: boolean;
        isPending: boolean;
    }>(),
    { saveLabel: 'Сохранить' },
);

const emit = defineEmits<{
    save: [];
    discard: [];
    dismiss: [];
}>();

const saveButtonEl = useTemplateRef<HTMLButtonElement>('saveButton');

// Фокус на сохранении: случайное нажатие Enter не сотрёт правки
onMounted(() => {
    saveButtonEl.value?.focus({ preventScroll: true });
});
</script>

<template>
    <div class="unsaved-prompt" role="alert">
        <p class="unsaved-prompt-text">Закрыть без сохранения?</p>

        <div class="unsaved-prompt-actions">
            <button
                type="button"
                class="unsaved-prompt-discard"
                :disabled="props.isPending"
                @click="emit('discard')"
            >
                Закрыть
            </button>

            <button
                ref="saveButton"
                type="button"
                class="unsaved-prompt-save"
                :disabled="props.isPending || !props.canSave"
                @click="emit('save')"
            >
                <LoaderButtonSpinner v-if="props.isPending" :size="14" />

                <span v-else>{{ props.saveLabel }}</span>
            </button>

            <!-- Убирает вопрос, окно остаётся открытым -->
            <button
                type="button"
                class="unsaved-prompt-dismiss"
                aria-label="Скрыть вопрос"
                :disabled="props.isPending"
                @click="emit('dismiss')"
            >
                <X :size="14" />
            </button>
        </div>
    </div>
</template>

<style scoped lang="scss">
.unsaved-prompt {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 8px 12px;
    margin: -8px 0 16px;
    padding: 8px 8px 8px 12px;
    border: 1px solid rgba(236, 72, 153, 0.35);
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.85);
    animation: unsavedPromptIn 0.18s ease-out;
}

.unsaved-prompt-text {
    margin: 0;
    color: var(--ink, #241533);
    font-size: 12px;
    font-weight: 600;
}

.unsaved-prompt-actions {
    display: flex;
    align-items: center;
    gap: 6px;
}

.unsaved-prompt-save,
.unsaved-prompt-discard {
    padding: 5px 10px;
    border: none;
    border-radius: 10px;
    font-family: inherit;
    font-size: 12px;
    cursor: pointer;
    transition: all 0.2s ease;

    &:focus-visible {
        outline: 2px solid #8b5cf6;
        outline-offset: 2px;
    }
}

.unsaved-prompt-discard {
    background: rgba(139, 92, 246, 0.1);
    color: #db2777;
    font-weight: 600;

    &:hover:not(:disabled) {
        background: rgba(236, 72, 153, 0.12);
    }
}

.unsaved-prompt-save {
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 84px;
    background: var(
        --brand-gradient,
        linear-gradient(135deg, #8b5cf6, #ec4899)
    );
    color: #fff;
    font-weight: 700;

    &:hover:not(:disabled) {
        box-shadow: 0 6px 16px -8px rgba(139, 92, 246, 0.6);
    }
}

.unsaved-prompt-discard,
.unsaved-prompt-save {
    &:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }
}

.unsaved-prompt-dismiss {
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    align-self: center;
    width: 22px;
    height: 22px;
    padding: 0;
    border: none;
    border-radius: 50%;
    background: transparent;
    color: var(--ink-soft, #6b5878);
    cursor: pointer;
    transition: all 0.2s ease;

    &:hover:not(:disabled) {
        background: rgba(139, 92, 246, 0.1);
        color: var(--ink, #241533);
    }

    &:focus-visible {
        outline: 2px solid #8b5cf6;
        outline-offset: 2px;
    }

    &:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }
}

@keyframes unsavedPromptIn {
    from {
        opacity: 0;
        transform: translateY(-4px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@media (prefers-reduced-motion: reduce) {
    .unsaved-prompt {
        animation: none;
    }
}
</style>
