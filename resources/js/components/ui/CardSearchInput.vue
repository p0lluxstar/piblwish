<script setup lang="ts">
import { Search, X } from '@lucide/vue';
import { computed, nextTick, ref } from 'vue';

// Поиск карточек в начале панели WishlistSortBar (composables/useCardSearch.ts):
// дашборд, архив и «Чужие списки». Сначала видна только лупа, по клику поле
// выдвигается из неё вправо. Пустое поле сворачивается при потере фокуса, поле
// с запросом остаётся открытым. Esc очищает запрос и сворачивает поле
const props = defineProps<{
    modelValue: string;
    placeholder: string;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();

const input = ref<HTMLInputElement | null>(null);
const toggle = ref<HTMLButtonElement | null>(null);
const isOpen = ref(false);

// Запрос могут задать или очистить и снаружи (кнопка сброса фильтров),
// поэтому поле с запросом открыто независимо от isOpen
const isExpanded = computed(() => isOpen.value || props.modelValue !== '');

const open = async (): Promise<void> => {
    isOpen.value = true;
    await nextTick();
    input.value?.focus();
};

const onBlur = (): void => {
    if (props.modelValue === '') isOpen.value = false;
};

// Фокус остаётся в поле для нового запроса
const clear = (): void => {
    emit('update:modelValue', '');
    input.value?.focus();
};

const close = (): void => {
    emit('update:modelValue', '');
    isOpen.value = false;
    toggle.value?.focus();
};
</script>

<template>
    <div class="search" :class="{ open: isExpanded }">
        <!-- mousedown.prevent: нажатие на лупу у открытого поля не снимает
             с него фокус, иначе пустое поле свернулось бы и открылось снова -->
        <button
            ref="toggle"
            class="search-toggle"
            type="button"
            aria-label="Поиск карточек"
            title="Поиск"
            :aria-expanded="isExpanded"
            :tabindex="isExpanded ? -1 : 0"
            @mousedown.prevent
            @click="open"
        >
            <Search :size="14" aria-hidden="true" />
        </button>
        <div class="search-field">
            <input
                ref="input"
                class="search-input"
                type="search"
                :value="modelValue"
                :placeholder="placeholder"
                aria-label="Поиск карточек"
                autocomplete="off"
                enterkeyhint="search"
                @input="
                    emit(
                        'update:modelValue',
                        ($event.target as HTMLInputElement).value,
                    )
                "
                @blur="onBlur"
                @keydown.esc="close"
            />
            <button
                v-if="modelValue"
                class="search-clear"
                type="button"
                aria-label="Очистить поиск"
                title="Очистить поиск"
                @click="clear"
            >
                <X :size="14" aria-hidden="true" />
            </button>
        </div>
    </div>
</template>

<style scoped lang="scss">
// Капсула в стиле кнопок фильтров на панели (.sort-btn в WishlistSortBar).
// Свёрнутая — только лупа; при открытии капсула расширяется вправо
// с 42px до 232px, и в ней появляется поле ввода. Ширину открытой капсулы
// на узком экране задаёт панель
.search {
    display: flex;
    align-items: center;
    box-sizing: border-box;
    width: 42px;
    height: 31px;
    min-width: 0;
    background: #fff;
    color: var(--ink-soft);
    border: 1.5px solid var(--surface-border);
    border-radius: 20px;
    overflow: hidden;
    transition:
        width 0.25s ease,
        color 0.2s ease,
        border-color 0.2s ease;

    &:hover,
    &:focus-within,
    &.open {
        color: var(--brand-violet);
        border-color: var(--brand-violet);
    }

    &.open {
        width: 232px;
    }
}

.search-toggle {
    display: flex;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    width: 39px;
    height: 100%;
    padding: 0;
    border: none;
    background: none;
    color: inherit;
    line-height: 0;
    cursor: pointer;

    .open & {
        cursor: text;
    }
}

.search-field {
    position: relative;
    display: flex;
    flex: 1;
    align-items: center;
    min-width: 0;
    height: 100%;
}

.search-input {
    box-sizing: border-box;
    width: 100%;
    min-width: 0;
    height: 100%;
    padding: 0 28px 0 0;
    background: transparent;
    color: var(--ink);
    border: none;
    font-size: 12px;
    font-weight: 600;
    font-family: inherit;
    outline: none;
    visibility: hidden;
    appearance: none;

    .open & {
        visibility: visible;
    }

    &::placeholder {
        color: var(--ink-soft);
        font-weight: 500;
        opacity: 0.8;
    }

    // Вместо встроенного крестика браузера — своя кнопка очистки
    &::-webkit-search-cancel-button {
        display: none;
    }
}

.search-clear {
    position: absolute;
    right: 5px;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 22px;
    height: 22px;
    padding: 0;
    border: none;
    border-radius: 50%;
    background: none;
    color: var(--ink-soft);
    cursor: pointer;
    transition:
        color 0.2s ease,
        background 0.2s ease;

    &:hover,
    &:focus-visible {
        color: var(--brand-violet);
        background: rgba(139, 92, 246, 0.08);
    }
}
</style>
