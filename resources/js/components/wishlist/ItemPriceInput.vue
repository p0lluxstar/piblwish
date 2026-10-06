<script setup lang="ts">
import { Tag } from '@lucide/vue';
import { computed, ref } from 'vue';

import { formatPriceDigits, parsePriceInput } from '../../lib/itemPrice';

// Стоимость позиции в модалках, в целых рублях. Пустое поле — null (стоимость не указана).
// Пока поле в фокусе, в нём только цифры; после ухода из поля разряды разделяются пробелами
const model = defineModel<number | null | undefined>();

defineProps<{
    // Подарок уже выбран гостем: поле серое, как приоритет и карточка
    muted?: boolean;
}>();

const focused = ref(false);

const text = computed(() => {
    if (model.value === null || model.value === undefined) return '';

    return focused.value ? String(model.value) : formatPriceDigits(model.value);
});

const handleInput = (event: { target: unknown }): void => {
    if (!(event.target instanceof window.HTMLInputElement)) return;

    const input = event.target;
    const value = parsePriceInput(input.value);
    const normalized = value === null ? '' : String(value);

    model.value = value;

    // Если ввод отброшен целиком (буква, лишняя цифра сверх максимума), значение
    // модели не меняется и Vue не обновит поле, поэтому оно исправляется вручную
    if (input.value !== normalized) {
        input.value = normalized;
    }
};
</script>

<template>
    <div :class="['price-input', { 'price-input--muted': muted }]">
        <!-- Значок перед полем, как у поля ссылки -->
        <Tag :size="13" class="price-input-icon" aria-hidden="true" />

        <input
            :value="text"
            type="text"
            inputmode="numeric"
            autocomplete="off"
            placeholder="Цена"
            aria-label="Стоимость в рублях"
            class="price-input-field"
            @input="handleInput"
            @focus="focused = true"
            @blur="focused = false"
        />

        <span class="price-input-currency" aria-hidden="true">₽</span>
    </div>
</template>

<style scoped lang="scss">
.price-input {
    position: relative;
    flex-shrink: 0;
    width: 116px;
}

// Как значок поля ссылки (.item-url-icon в wishlistModal.scss)
.price-input-icon {
    position: absolute;
    left: 11px;
    top: 50%;
    transform: translateY(-50%);
    color: rgba(139, 92, 246, 0.55);
    pointer-events: none;
}

.price-input-field {
    width: 100%;
    height: 26px;
    padding: 0 22px 0 30px;
    border: 1.5px solid rgba(139, 92, 246, 0.15);
    border-radius: 9px;
    background: #faf8ff;
    font-size: 12px;
    font-weight: 600;
    color: #ec4899;
    text-align: right;
    transition: all 0.18s ease;

    &::placeholder {
        color: #b3a3bd;
        font-weight: 400;
        text-align: left;
    }

    &:focus {
        outline: none;
        border-color: #8b5cf6;
        background: #fff;
        box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.12);
    }
}

.price-input-currency {
    position: absolute;
    right: 9px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 12px;
    color: #b3a3bd;
    pointer-events: none;
}

.price-input--muted {
    .price-input-field,
    .price-input-icon {
        color: #94a3b8;
    }
}
</style>
