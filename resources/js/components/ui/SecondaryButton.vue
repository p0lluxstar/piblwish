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
    transform: translateY(-3px);
}

.secondary {
    color: #56345e;
    background: rgba(255, 255, 255, 0.72);
    border: 1px solid rgba(181, 139, 166, 0.26);
    box-shadow: 0 14px 30px rgba(92, 62, 97, 0.08);
    backdrop-filter: blur(12px);
}

.secondary:hover {
    background: #ffffff;
}
</style>
