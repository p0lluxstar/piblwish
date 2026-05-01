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
    border: 1px solid rgba(181, 139, 166, 0.28);
    border-radius: 18px;

    padding: 16px 18px;

    background: rgba(255, 255, 255, 0.78);

    color: #3b2146;
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
    border-color: #ff8fab;
    background: #ffffff;

    box-shadow: 0 0 0 4px rgba(255, 143, 171, 0.16);
}
</style>
