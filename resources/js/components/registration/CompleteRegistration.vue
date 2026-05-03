<script setup lang="ts">
import PrimaryButton from '@/components/ui/PrimaryButton.vue';
</script>

<template>
    <div class="header">
        <p class="eyebrow">Подтверждение регистрации</p>
    </div>

    <h2>Регистрация подтверждена!</h2>

    <PrimaryButton @click="$router.push('/login')">
        <span>Войти в аккаунт</span>
    </PrimaryButton>
    <!-- <router-link to="/login" class="login-btn">Войти в аккаунт</router-link> -->
</template>

<style scoped>
h2 {
    margin-bottom: 50px;
    color: #3b2146;
}
</style>
