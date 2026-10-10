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
.due-date-field {
    display: flex;
    flex-direction: column;
}

// Как подписи полей в wishlistModal.scss
.due-date-label {
    margin-bottom: 8px;
    font-weight: 600;
    font-size: 13px;
    color: var(--ink, #241533);
}

.due-date-optional {
    font-weight: 500;
    color: var(--ink-soft, #6b5878);
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
    color: var(--ink, #241533);
    background: #faf8ff;
    transition: all 0.18s ease;

    &:focus {
        outline: none;
        border-color: #8b5cf6;
        background: #fff;
        box-shadow: 0 0 0 4px rgba(139, 92, 246, 0.12);
    }
}

.due-date-clear {
    padding: 0;
    border: none;
    background: none;
    color: #8b5cf6;
    font-size: 12px;
    font-weight: 600;
    font-family: inherit;
    cursor: pointer;

    &:hover {
        text-decoration: underline;
    }
}

.due-date-hint {
    margin-top: 6px;
    font-size: 12px;
    color: var(--ink-soft, #6b5878);
}
</style>
