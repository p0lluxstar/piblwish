<script setup lang="ts">
interface Props {
    modelValue: string;
    type?: string;
    placeholder?: string;
    code?: string;
}

withDefaults(defineProps<Props>(), {
    type: 'text',
    placeholder: '',
    code: '',
});

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();

function updateValue(event: Event): void {
    const target = event.target as HTMLInputElement;
    emit('update:modelValue', target.value);
}
</script>

<template>
    <input
        class="input"
        :value="modelValue"
        :type="type"
        :placeholder="placeholder"
        :code="code"
        @input="updateValue"
    />
</template>

<style scoped>
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

.input::placeholder {
    color: #a1849f;
}

.input:focus {
    border-color: var(--brand-violet, #8b5cf6);
    background: #ffffff;
    box-shadow: 0 0 0 4px rgba(139, 92, 246, 0.14);
}
</style>
