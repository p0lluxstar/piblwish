<script setup lang="ts">
import { ref } from 'vue';

import RegistrationForm from '@/components/registration/RegistrationForm.vue';
import VerificationCodeForm from '@/components/registration/VerificationCodeForm.vue';
import BackOnMainPage from '@/components/ui/BackOnMainPage.vue';

const step = ref<'register' | 'verify'>('register');
const verificationEmail = ref('');

function handleRegisterSuccess(email: string): void {
    verificationEmail.value = email;
    step.value = 'verify';
}

function handleVerificationSuccess(): void {
    console.log('Код подтвержден');
}
</script>

<template>
    <div class="content">
        <div class="header">
            <BackOnMainPage />
            <p class="eyebrow">
                {{ step === 'register' ? 'Регистрация' : 'Подтверждение' }}
            </p>
        </div>

        <h2>
            {{
                step === 'register'
                    ? 'Создать аккаунт'
                    : 'Введите код подтверждения'
            }}
        </h2>

        <RegistrationForm
            v-if="step === 'register'"
            @success="handleRegisterSuccess"
        />

        <VerificationCodeForm
            v-else
            :email="verificationEmail"
            @success="handleVerificationSuccess"
        />

        <p v-if="step === 'register'" class="auth-link">
            Уже зарегистрированы?
            <router-link to="/login">Войти</router-link>
        </p>
    </div>
</template>

<style scoped>
.content {
    max-width: 530px;
    animation: fadeInUp 0.6s ease;
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
