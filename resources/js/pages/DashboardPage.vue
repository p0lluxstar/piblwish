<script setup lang="ts">
import Dashboard from '@/components/dashboard/Dashboard.vue';

defineProps<{
    // Архив списков (/dashboard/archive)
    archived?: boolean;
}>();
</script>

<template>
    <Dashboard :archived="archived" />
</template>

<style scoped></style>
