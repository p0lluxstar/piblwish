<script setup lang="ts">
import Footer from '@/components/dashboard/Footer.vue';
import Header from '@/components/dashboard/Header.vue';
import Main from '@/components/dashboard/Main.vue';
import ScrollToTopButton from '@/components/ui/ScrollToTopButton.vue';
</script>

<template>
    <div class="dashboard">
        <div class="dashboard-glow dashboard-glow--violet"></div>
        <div class="dashboard-glow dashboard-glow--pink"></div>
        <div class="dashboard-glow dashboard-glow--amber"></div>

        <Header />
        <Main />
        <Footer />
        <ScrollToTopButton />
    </div>
</template>

<style scoped>
.dashboard {
    position: relative;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    background: linear-gradient(160deg, #f7f4ff 0%, #fff0f6 45%, #fffaf0 100%);
    overflow-x: hidden;
    isolation: isolate;
}

.dashboard-glow {
    position: fixed;
    z-index: -1;
    border-radius: 50%;
    filter: blur(90px);
    opacity: 0.35;
    pointer-events: none;
}

.dashboard-glow--violet {
    top: -120px;
    left: -100px;
    width: 420px;
    height: 420px;
    background: #a78bfa;
}

.dashboard-glow--pink {
    top: 20%;
    right: -140px;
    width: 380px;
    height: 380px;
    background: #f9a8d4;
}

.dashboard-glow--amber {
    bottom: -140px;
    left: 30%;
    width: 360px;
    height: 360px;
    background: #fde68a;
    opacity: 0.3;
}
</style>
