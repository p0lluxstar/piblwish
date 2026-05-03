<script setup lang="ts">
import { ref } from 'vue';

import CompleteRegistration from '@/components/registration/CompleteRegistration.vue';
import RegistrationForm from '@/components/registration/RegistrationForm.vue';
import VerificationRegistrationForm from '@/components/registration/VerificationRegistrationForm.vue';

const step = ref<'register' | 'verify' | 'complete'>('register');
const verificationEmail = ref('');

function handleRegisterSuccess(email: string): void {
    verificationEmail.value = email;
    step.value = 'verify';
}

function handleVerificationSuccess(): void {
    console.log('Код подтвержден');
    step.value = 'complete';
}
</script>

<template>
    <div class="content">
        <!-- 1. Форма регистрации -->
        <RegistrationForm
            v-if="step === 'register'"
            @success="handleRegisterSuccess"
        />

        <!-- 2. Форма подтверждения -->
        <VerificationRegistrationForm
            v-else-if="step === 'verify'"
            :email="verificationEmail"
            @success="handleVerificationSuccess"
        />

        <!-- 3. Финальный экран успеха -->
        <CompleteRegistration v-else-if="step === 'complete'" />

        <p v-if="step === 'register'" class="auth-link">
            Уже зарегистрированы?
            <router-link to="/login">Войти</router-link>
        </p>
    </div>
</template>

<style scoped></style>
