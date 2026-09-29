<script setup lang="ts"></script>

<template>
    <main class="main">
        <div class="conteiner">
            <div><router-view /></div>
        </div>
    </main>
</template>

<style scoped>
.main {
    flex: 1;
    padding: 36px 28px;
}

.conteiner {
    max-width: 1200px;
    margin: 0 auto;
    color: var(--ink);
}
</style>
