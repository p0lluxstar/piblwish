<script setup lang="ts">
import { computed, ref, watch } from 'vue';

import FormErrorMessage from '@/components/ui/FormErrorMessage.vue';
import { useUpdateReminderDays } from '@/composables/useAuth';
import { useAuthStore } from '@/stores/auth';

// Срок напоминаний на почту: за сколько дней до даты списка приходит письмо.
// Выбирается ползунком и сохраняется, когда его отпускают; при выключенных
// напоминаниях неактивен
const MIN_DAYS = 1;
const MAX_DAYS = 7;
const DAYS = Array.from(
    { length: MAX_DAYS - MIN_DAYS + 1 },
    (_, index) => MIN_DAYS + index,
);

// Срок по умолчанию, как на бэкенде (DueDateReminderService::DEFAULT_DAYS)
const DEFAULT_DAYS = 3;

const auth = useAuthStore();
const { mutate: updateReminderDays, errorMessage } = useUpdateReminderDays();

const selected = computed(
    (): number => auth.user?.reminderDays ?? DEFAULT_DAYS,
);
const isDisabled = computed((): boolean => auth.user?.emailReminders === false);

// Положение ползунка во время перетаскивания; после сохранения или его отката
// возвращается к значению из стора
const draft = ref(selected.value);

watch(selected, (value) => {
    draft.value = value;
});

// «1 день», «3 дня», «5 дней»
const daysLabel = computed((): string => {
    const days = draft.value;

    if (days === 1) return '1 день';
    if (days >= 2 && days <= 4) return `${days} дня`;

    return `${days} дней`;
});

// Доля заполненной части дорожки слева от ползунка
const fillPercent = computed(
    (): string =>
        `${((draft.value - MIN_DAYS) / (MAX_DAYS - MIN_DAYS)) * 100}%`,
);

const handleInput = (event: Event): void => {
    draft.value = Number((event.target as HTMLInputElement).value);
};

const save = (): void => {
    if (draft.value !== selected.value) {
        updateReminderDays(draft.value);
    }
};
</script>

<template>
    <div :class="['reminder-days', { 'is-disabled': isDisabled }]">
        <label for="reminder-days-range" class="reminder-days-label">
            <span>За сколько дней напоминать</span>
            <span class="reminder-days-value">{{ daysLabel }}</span>
        </label>

        <input
            id="reminder-days-range"
            class="reminder-days-range"
            type="range"
            :min="MIN_DAYS"
            :max="MAX_DAYS"
            step="1"
            :value="draft"
            :disabled="isDisabled"
            :aria-valuetext="daysLabel"
            :style="{ '--fill': fillPercent }"
            @input="handleInput"
            @change="save"
        />

        <div class="reminder-days-ticks" aria-hidden="true">
            <span
                v-for="days in DAYS"
                :key="days"
                :class="{ 'is-active': days === draft }"
            >
                {{ days }}
            </span>
        </div>

        <FormErrorMessage
            class="error-message"
            :show="!!errorMessage"
            :message="errorMessage ?? undefined"
        />
    </div>
</template>

<style scoped lang="scss">
$thumb-size: 16px;

.reminder-days {
    margin-top: 10px;
    transition: opacity 0.18s ease;

    &.is-disabled {
        opacity: 0.5;
    }
}

// Как заголовок переключателя (.toggle-switch-title)
.reminder-days-label {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    gap: 8px;
    margin-bottom: 8px;
    font-size: 13px;
    font-weight: 600;
    color: var(--ink);
}

.reminder-days-value {
    flex-shrink: 0;
    color: var(--brand-violet);
}

@mixin thumb {
    width: $thumb-size;
    height: $thumb-size;
    border: 2px solid var(--brand-violet);
    border-radius: 50%;
    background: var(--surface-solid);
    box-shadow: 0 2px 6px rgba(139, 92, 246, 0.35);
    cursor: pointer;
    transition: transform 0.15s ease;
}

.reminder-days-range {
    display: block;
    width: 100%;
    height: $thumb-size;
    margin: 0;
    background: transparent;
    cursor: pointer;
    appearance: none;

    &::-webkit-slider-runnable-track {
        height: 4px;
        border-radius: 2px;
        background: linear-gradient(
            to right,
            var(--brand-violet) var(--fill),
            rgba(139, 92, 246, 0.18) var(--fill)
        );
    }

    &::-webkit-slider-thumb {
        @include thumb;
        margin-top: calc((4px - #{$thumb-size}) / 2);
        appearance: none;
    }

    &::-moz-range-track {
        height: 4px;
        border-radius: 2px;
        background: rgba(139, 92, 246, 0.18);
    }

    &::-moz-range-progress {
        height: 4px;
        border-radius: 2px;
        background: var(--brand-violet);
    }

    &::-moz-range-thumb {
        @include thumb;
    }

    &:hover:not(:disabled)::-webkit-slider-thumb {
        transform: scale(1.1);
    }

    &:hover:not(:disabled)::-moz-range-thumb {
        transform: scale(1.1);
    }

    &:focus-visible {
        outline: 2px solid var(--brand-violet);
        outline-offset: 4px;
        border-radius: 4px;
    }

    &:disabled,
    &:disabled::-webkit-slider-thumb {
        cursor: default;
    }

    &:disabled::-moz-range-thumb {
        cursor: default;
    }
}

// Подписи делений: крайние стоят под центром ползунка в крайних положениях
.reminder-days-ticks {
    display: flex;
    justify-content: space-between;
    margin-top: 4px;
    padding: 0 calc(#{$thumb-size} / 2 - 4px);
    font-size: 11px;
    color: var(--ink-soft);

    span {
        width: 8px;
        text-align: center;
    }

    .is-active {
        font-weight: 700;
        color: var(--brand-violet);
    }
}

.error-message {
    margin: 10px 0 0;
    font-size: 13px;
}
</style>
