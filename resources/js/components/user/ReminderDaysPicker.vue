<script setup lang="ts">
import { computed } from 'vue';

import FormErrorMessage from '@/components/ui/FormErrorMessage.vue';
import { useUpdateReminderDays } from '@/composables/useAuth';
import { useAuthStore } from '@/stores/auth';

// Срок напоминаний на почту: за сколько дней до даты списка приходит письмо.
// Сохраняется сразу, как переключатели; при выключенных напоминаниях неактивен
const DAYS = [1, 2, 3, 4, 5, 6, 7];

// Срок по умолчанию, как на бэкенде (DueDateReminderService::DEFAULT_DAYS)
const DEFAULT_DAYS = 3;

const auth = useAuthStore();
const { mutate: updateReminderDays, errorMessage } = useUpdateReminderDays();

const selected = computed(
    (): number => auth.user?.reminderDays ?? DEFAULT_DAYS,
);
const isDisabled = computed((): boolean => auth.user?.emailReminders === false);

const select = (days: number): void => {
    if (days !== selected.value) {
        updateReminderDays(days);
    }
};
</script>

<template>
    <div :class="['reminder-days', { 'is-disabled': isDisabled }]">
        <span id="reminder-days-label" class="reminder-days-label">
            За сколько дней напоминать
        </span>

        <div
            class="reminder-days-options"
            role="radiogroup"
            aria-labelledby="reminder-days-label"
        >
            <button
                v-for="days in DAYS"
                :key="days"
                type="button"
                role="radio"
                :aria-checked="days === selected"
                :disabled="isDisabled"
                :class="[
                    'reminder-days-option',
                    { 'is-active': days === selected },
                ]"
                @click="select(days)"
            >
                {{ days }}
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
.reminder-days {
    margin-top: 10px;
    transition: opacity 0.18s ease;

    &.is-disabled {
        opacity: 0.5;
    }
}

// Как заголовок переключателя (.toggle-switch-title)
.reminder-days-label {
    display: block;
    margin-bottom: 8px;
    font-size: 13px;
    font-weight: 600;
    color: var(--ink, #241533);
}

.reminder-days-options {
    display: flex;
    gap: 6px;
}

.reminder-days-option {
    flex: 1;
    max-width: 40px;
    height: 32px;
    border: 1px solid rgba(139, 92, 246, 0.25);
    border-radius: 10px;
    background: #fff;
    font-size: 13px;
    font-weight: 600;
    color: var(--ink, #241533);
    cursor: pointer;
    transition:
        background 0.18s ease,
        color 0.18s ease,
        border-color 0.18s ease;

    &:hover:not(:disabled, .is-active) {
        border-color: var(--brand-violet, #8b5cf6);
    }

    &:focus-visible {
        outline: 2px solid var(--brand-violet, #8b5cf6);
        outline-offset: 2px;
    }

    &:disabled {
        cursor: default;
    }

    &.is-active {
        border-color: transparent;
        background: var(
            --brand-gradient,
            linear-gradient(135deg, #8b5cf6, #ec4899)
        );
        color: #fff;
    }
}

.error-message {
    margin: 10px 0 0;
    font-size: 13px;
}
</style>
