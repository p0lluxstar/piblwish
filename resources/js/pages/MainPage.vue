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

<style scoped>
.content {
    max-width: 530px;
    animation: fadeInUp 0.6s ease;
}

.eyebrow {
    margin: 0 0 14px;
    color: #d9467c;
    font-size: 14px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.16em;
}

.content h2 {
    margin: 0;
    max-width: 580px;
    font-size: clamp(40px, 5vw, 70px);
    font-weight: 600;
    line-height: 1;
    letter-spacing: -0.075em;
    color: #2d1836;
}

.description {
    max-width: 460px;
    margin: 24px 0 0;
    color: #7c6078;
    font-size: 22px;
    line-height: 1.5;
}

.actions {
    margin-top: 42px;
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
