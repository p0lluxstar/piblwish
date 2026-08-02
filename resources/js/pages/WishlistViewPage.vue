<script setup lang="ts">
import WishlistView from '@/components/wishlist/WishlistView.vue';
</script>

<template>
    <main class="main">
        <div class="content">
            <WishlistView />
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
