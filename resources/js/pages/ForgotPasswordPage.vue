<script setup lang="ts">
import { ref } from 'vue';

import ForgotPasswordForm from '@/components/password-reset/ForgotPasswordForm.vue';
import ResetPasswordComplete from '@/components/password-reset/ResetPasswordComplete.vue';
import ResetPasswordForm from '@/components/password-reset/ResetPasswordForm.vue';

const step = ref<'request' | 'reset' | 'complete'>('request');
const resetEmail = ref('');

function handleCodeRequested(email: string): void {
    resetEmail.value = email;
    step.value = 'reset';
}
</script>

<template>
    <div class="content">
        <!-- 1. Запрос кода на email -->
        <ForgotPasswordForm
            v-if="step === 'request'"
            :initial-email="resetEmail"
            @success="handleCodeRequested"
        />

        <!-- 2. Ввод кода и нового пароля -->
        <ResetPasswordForm
            v-else-if="step === 'reset'"
            :email="resetEmail"
            @success="step = 'complete'"
            @change-email="step = 'request'"
        />

        <!-- 3. Пароль изменён -->
        <ResetPasswordComplete v-else />

        <p v-if="step === 'request'" class="auth-link">
            Вспомнили пароль?
            <router-link to="/login">Войти</router-link>
        </p>
    </div>
</template>

<style scoped></style>
