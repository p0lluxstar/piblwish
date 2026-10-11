<script setup lang="ts">
import BackOnMainPage from '@/components/ui/BackOnMainPage.vue';
import PrimaryButton from '@/components/ui/PrimaryButton.vue';
</script>

<template>
    <div class="header">
        <BackOnMainPage />

        <p class="eyebrow">Восстановление пароля</p>
    </div>

    <h2>Пароль изменён</h2>

    <p class="hint">
        Войдите с новым паролем. Сеансы на всех устройствах завершены.
    </p>

    <PrimaryButton @click="$router.push('/login')">
        <span>Войти в аккаунт</span>
    </PrimaryButton>
</template>

<style scoped>
h2 {
    color: var(--ink);
}

.hint {
    max-width: 420px;
    margin: 12px 0 40px;
    color: var(--muted, #7a6278);
    line-height: 1.5;
}
</style>
