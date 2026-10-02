<script setup lang="ts">
import WishlistShared from '@/components/wishlist/WishlistShared.vue';
</script>

<template>
    <div class="shared-page">
        <WishlistShared />
    </div>
</template>

<style scoped>
/* Не используем класс .content: в app.scss для него задан глобальный max-width лендинга */
.shared-page {
    display: flex;
    justify-content: center;
    width: 100%;
}
</style>
