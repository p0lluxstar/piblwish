<script setup lang="ts">
import { computed, useId } from 'vue';

import {
    type DueDateStatus,
    dueDateTitle,
    getDueDateStatus,
} from '../../lib/dueDate';
import type { WishlistType } from '../../types/wishlist';

// Дата списка в окнах создания и редактирования: срок у списка дел, дата события
// у списка желаний. Пустая строка — дата не указана
const model = defineModel<string>({ required: true });

const props = defineProps<{
    type: WishlistType;
}>();

const inputId = useId();

const title = computed(() => dueDateTitle(props.type));

// Под полем — сколько осталось до выбранной даты, как на карточке.
// Выполненность дел здесь не учитывается: подсказка относится к самой дате
const status = computed<DueDateStatus | null>(() =>
    model.value ? getDueDateStatus(props.type, model.value, false) : null,
);
</script>

<template>
    <div class="due-date-field">
        <label :for="inputId" class="due-date-label">
            {{ title }}
            <span class="due-date-optional">(необязательно)</span>
        </label>

        <div class="due-date-row">
            <!-- Границы совпадают с проверкой на сервере -->
            <input
                :id="inputId"
                v-model="model"
                class="due-date-input"
                type="date"
                min="2000-01-01"
                max="2099-12-31"
            />

            <button
                v-if="model"
                class="due-date-clear"
                type="button"
                @click="model = ''"
            >
                Убрать дату
            </button>
        </div>

        <span v-if="status" class="due-date-hint">{{ status.text }}</span>
    </div>
</template>

<style scoped lang="scss">
// Подсказка под полем выводится поверх отступа .form-group (16px) до следующего
// блока, чтобы её появление не сдвигало форму; её строка (12px) в нём помещается
.due-date-field {
    position: relative;
    display: flex;
    flex-direction: column;
}

// Как подписи полей в wishlistModal.scss
.due-date-label {
    margin-bottom: 8px;
    font-weight: 600;
    font-size: 13px;
    color: var(--ink);
}

.due-date-optional {
    font-weight: 500;
    color: var(--ink-soft);
}

.due-date-row {
    display: flex;
    align-items: center;
    gap: 12px;
}

// Как поля ввода в wishlistModal.scss
.due-date-input {
    flex: 0 1 200px;
    min-width: 0;
    padding: 10px 14px;
    border: 1.5px solid rgba(139, 92, 246, 0.15);
    border-radius: 14px;
    font-size: 14px;
    font-family: inherit;
    color: var(--ink);
    background: var(--surface-soft);
    transition: all 0.18s ease;

    &:focus {
        outline: none;
        border-color: var(--brand-violet);
        background: var(--surface-solid);
        box-shadow: 0 0 0 4px rgba(139, 92, 246, 0.12);
    }
}

.due-date-clear {
    padding: 0;
    border: none;
    background: none;
    color: var(--brand-violet);
    font-size: 12px;
    font-weight: 600;
    font-family: inherit;
    cursor: pointer;

    &:hover {
        text-decoration: underline;
    }
}

.due-date-hint {
    position: absolute;
    top: calc(100% + 0px);
    left: 0;
    font-size: 10px;
    line-height: 12px;
    white-space: nowrap;
    // Как пояснения переключателей в той же форме (ToggleSwitch)
    color: var(--ink-muted);
}
</style>
