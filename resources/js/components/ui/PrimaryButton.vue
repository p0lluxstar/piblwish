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

.btn:hover:not(:disabled) {
    transform: translateY(-3px) scale(1.02);
}

.btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.primary {
    color: white;
    background: var(--brand-gradient, linear-gradient(135deg, #8b5cf6, #ec4899));
    box-shadow: var(--shadow-glow-lg, 0 22px 48px -16px rgba(139, 92, 246, 0.4));
}
</style>
