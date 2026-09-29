<template>
    <button class="btn secondary" @click="$emit('click')">
        <slot />
    </button>
</template>

<script setup lang="ts">
defineEmits(['click']);
</script>

<style scoped>
.btn {
    border: 0;
    border-radius: 999px;
    padding: 16px 30px;
    font-size: 16px;
    font-weight: 800;
    cursor: pointer;
    transition:
        transform 0.2s ease,
        box-shadow 0.2s ease,
        background 0.2s ease;
}

.btn:hover {
    transform: translateY(-3px) scale(1.02);
}

.secondary {
    color: var(--brand-violet, #8b5cf6);
    background: var(--surface, rgba(255, 255, 255, 0.82));
    border: 1.5px solid var(--surface-border, rgba(139, 92, 246, 0.14));
    box-shadow: 0 14px 30px rgba(92, 62, 97, 0.08);
    backdrop-filter: blur(12px);
}

.secondary:hover {
    background: #ffffff;
    box-shadow: var(--shadow-glow, 0 14px 34px -12px rgba(139, 92, 246, 0.38));
}
</style>
