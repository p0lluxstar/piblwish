<script setup lang="ts">
import WishlistShared from '@/components/wishlist/WishlistShared.vue';
</script>

<template>
    <main class="main">
        <div class="content">
            <WishlistShared />
        </div>
    </main>
</template>

<style scoped>
.main {
    display: flex;
    justify-content: center;
    background: #fff8fb;
}

.content {
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100vh;
    max-width: 1200px;
    padding-top: 0px;
}
</style>
