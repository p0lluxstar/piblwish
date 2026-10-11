<script setup lang="ts">
import { computed } from 'vue';

import PasswordToggle from '@/components/ui/PasswordToggle.vue';

interface Props {
    modelValue: string;
    type?: string;
    placeholder?: string;
    code?: string;
    autocomplete?: string;
    // Кнопка-глаз у поля пароля; у поля подтверждения её отключают и
    // связывают его с основным полем через v-model:revealed
    toggle?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    type: 'text',
    placeholder: '',
    code: '',
    autocomplete: undefined,
    toggle: true,
});

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();

// Показан ли пароль; без v-model:revealed хранится внутри поля
const revealed = defineModel<boolean>('revealed', { default: false });

const isPassword = computed(() => props.type === 'password');
const hasToggle = computed(() => isPassword.value && props.toggle);

const inputType = computed(() =>
    isPassword.value && revealed.value ? 'text' : props.type,
);

function updateValue(event: Event): void {
    const target = event.target as HTMLInputElement;
    emit('update:modelValue', target.value);
}
</script>

<template>
    <div class="field">
        <input
            class="input"
            :class="{ 'input--with-toggle': hasToggle }"
            :value="modelValue"
            :type="inputType"
            :placeholder="placeholder"
            :code="code"
            :autocomplete="autocomplete"
            @input="updateValue"
        />

        <PasswordToggle v-if="hasToggle" v-model="revealed" />
    </div>
</template>

<style scoped>
.field {
    position: relative;
}

.input {
    width: 100%;
    border: 1.5px solid var(--surface-border, rgba(139, 92, 246, 0.14));
    border-radius: 16px;
    padding: 16px 18px;
    background: rgba(255, 255, 255, 0.78);
    color: var(--ink, #241533);
    font-size: 16px;
    outline: none;
    box-shadow: 0 12px 28px rgba(92, 62, 97, 0.06);
    transition:
        border-color 0.2s ease,
        box-shadow 0.2s ease,
        background 0.2s ease;
}

.input--with-toggle {
    padding-right: 52px;
}

.input::placeholder {
    color: #a1849f;
}

.input:focus {
    border-color: var(--brand-violet, #8b5cf6);
    background: #ffffff;
    box-shadow: 0 0 0 4px rgba(139, 92, 246, 0.14);
}
</style>
