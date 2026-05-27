<template>
    <div class="loader-container flex items-center justify-center">
        <div class="flex justify-center items-center">
            <div class="text-center">
                <div
                    class="animate-spin rounded-full h-12 w-12 border-b-2 border-honor-blue mx-auto mb-4"
                ></div>
                <p class="text-honor-darkGray">{{ loadingText }}</p>
            </div>
        </div>
    </div>
</template>

<script setup>
defineProps({
    loadingText: {
        type: String,
        default: 'Загрузка...',
    },
});
</script>

<style scoped>
.loader-container {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100vh;
    /* или height: 100% если у body и html height: 100% */
}
</style>
