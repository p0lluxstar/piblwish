<script setup lang="ts">
import Footer from '@/components/Footer.vue';
import Header from '@/components/Header.vue';
import Main from '@/components/Main.vue';
</script>

<template>
    <div class="dashboard">
        <Header />
        <Main />
        <Footer />
    </div>
</template>

<style scoped>
.dashboard {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
}
</style>
