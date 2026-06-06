<script setup lang="ts">
import { LoaderCircle } from '@lucide/vue';

withDefaults(
    defineProps<{
        size?: number;
    }>(),
    {
        size: 24,
    },
);
</script>

<template>
    <LoaderCircle
        class="spinner"
        :style="{ width: `${size}px`, height: `${size}px` }"
    />
</template>

<style scoped>
.spinner {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}
</style>
