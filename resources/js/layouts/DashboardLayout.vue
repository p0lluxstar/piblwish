<script setup lang="ts">
import Footer from '@/components/dashboard/Footer.vue';
import Header from '@/components/dashboard/Header.vue';
import Main from '@/components/dashboard/Main.vue';
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
