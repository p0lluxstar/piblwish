<script setup lang="ts">
defineProps<{
    show: boolean;
    message?: string;
}>();

</script>

<template>
    <p v-if="show" class="error-message">
        {{ message || 'Не удалось выполнить действие. Попробуйте позже.' }}
    </p>
</template>

<style scoped>
.error-message {
    max-width: 420px;
    margin: 0;
    color: #d14343;
    font-size: 14px;
    line-height: 1.4;
    text-align: center;
}
</style>
