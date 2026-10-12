<script setup lang="ts">
import { House } from '@lucide/vue';
</script>

<template>
    <RouterLink
        to="/"
        class="back-link"
        aria-label="На главную"
        title="На главную"
    >
        <House :size="17" :stroke-width="2.4" />
    </RouterLink>
</template>

<style scoped>
.back-link {
    display: flex;
    justify-content: center;
    align-items: center;
    border-radius: 50%;
    text-decoration: none;
    background: var(--surface-solid);
    color: var(--brand-pink);
    box-shadow: var(--shadow-glow);
    width: 34px;
    height: 34px;
    margin-bottom: 20px;
    transition: all 0.2s ease;

    &:hover {
        background: var(--brand-gradient);
        color: #fff;
        transform: translateY(-2px);
    }
}
</style>
