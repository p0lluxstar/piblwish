<script setup lang="ts">
import { reactive } from 'vue';

import BackOnMainPage from '@/components/ui/BackOnMainPage.vue';
import MainInputForm from '@/components/ui/MainInputForm.vue';
import PrimaryButton from '@/components/ui/PrimaryButton.vue';

const form = reactive({
    email: '',
    password: '',
});

function submitForm(): void {
    console.log(form);
}
</script>

<template>
    <div class="content">
        <div class="header">
            <BackOnMainPage />

            <p class="eyebrow">Вход</p>
        </div>

        <h2>Войдите в аккаунт</h2>

        <form class="auth-form" @submit.prevent="submitForm">
            <MainInputForm
                v-model="form.email"
                type="email"
                placeholder="Email"
            />

            <MainInputForm
                v-model="form.password"
                type="password"
                placeholder="Пароль"
            />

            <PrimaryButton type="submit">Войти</PrimaryButton>
        </form>

        <p class="auth-link">
            Нет аккаунта?
            <router-link to="/registration">Зарегистрироваться</router-link>
        </p>
    </div>
</template>

<style scoped>
.content {
    max-width: 530px;
    animation: fadeInUp 0.6s ease;
}

.auth-form {
    margin-top: 32px;
    max-width: 420px;
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.header {
    display: flex;
    align-items: center;
    gap: 24px;
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

.auth-link {
    max-width: 420px;
    margin-top: 24px;
    text-align: center;
    color: #7c6078;
}

.auth-link a {
    margin-left: 6px;
    color: #d9467c;
    font-weight: 700;
    text-decoration: none;
}

.auth-link a:hover {
    text-decoration: underline;
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
