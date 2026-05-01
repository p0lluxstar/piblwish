<script setup lang="ts">
defineProps<{
    disabled?: boolean;
}>();

defineEmits(['click']);
</script>

<template>
    <button :disabled="disabled" class="btn primary" @click="$emit('click')">
        <slot />
    </button>
</template>

<style scoped>
.btn {
    display: flex;
    justify-content: center;
    align-items: center;
    border: 0;
    border-radius: 999px;
    padding: 16px 30px;
    font-size: 16px;
    font-weight: 800;
    cursor: pointer;
    height: 56px;
    transition:
        transform 0.2s ease,
        box-shadow 0.2s ease,
        background 0.2s ease;
}

.btn:hover {
    transform: translateY(-3px);
}

.primary {
    color: white;
    background: linear-gradient(135deg, #ff7aa8, #a78bfa);
    box-shadow: 0 18px 34px rgba(255, 122, 168, 0.32);
}
</style>
