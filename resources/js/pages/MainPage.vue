<script setup lang="ts">
import { useRouter } from 'vue-router';

import PrimaryButton from '@/components/ui/PrimaryButton.vue';
import SecondaryButton from '@/components/ui/SecondaryButton.vue';

const router = useRouter();

function register(): void {
    router.push('/registration');
}

function login(): void {
    router.push('/login');
}
</script>

<template>
    <div class="content">
        <p class="eyebrow">PiblWish</p>

        <h2>Сохраняйте желания красиво</h2>

        <p class="description">
            Создавайте списки желаний, делитесь ими с друзьями и не теряйте идеи
            для подарков, покупок и важных моментов.
        </p>

        <div class="actions">
            <PrimaryButton @click="register">Регистрация</PrimaryButton>
            <SecondaryButton @click="login">Войти</SecondaryButton>
        </div>
    </div>
</template>

<style lang="scss" scoped>

</style>
