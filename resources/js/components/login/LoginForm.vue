<script setup lang="ts">
import { useMutation } from '@tanstack/vue-query';
import { reactive } from 'vue';
import { useRouter } from 'vue-router';

import BackOnMainPage from '@/components/ui/BackOnMainPage.vue';
import FormErrorMessage from '@/components/ui/FormErrorMessage.vue';
import InputRegistationForms from '@/components/ui/InputRegistationForms.vue';
import LoaderButtonSpinner from '@/components/ui/LoaderButtonSpinner.vue';
import PrimaryButton from '@/components/ui/PrimaryButton.vue';
import { api } from '@/lib/api';

type LoginPayload = {
    email: string;
    password: string;
};

const router = useRouter();

const form = reactive({
    email: '',
    password: '',
});

const loginMutation = useMutation({
    mutationFn: (payload: LoginPayload) => api.post('/v1/login', payload),
    onSuccess: (data) => {
        console.log('Успешный вход:', data);

        router.push('/dashboard');
    },
});

const isLoading = loginMutation.isPending;
const isError = loginMutation.isError;

async function submitForm(): Promise<void> {
    try {
        await loginMutation.mutateAsync({
            email: form.email,
            password: form.password,
        });
    } catch (error) {
        console.error(error);
    }
}
</script>

<template>
    <div class="header">
        <BackOnMainPage />

        <p class="eyebrow">Вход</p>
    </div>

    <h2>Войдите в аккаунт</h2>

    <form class="auth-form" @submit.prevent="submitForm">
        <InputRegistationForms
            v-model="form.email"
            type="email"
            placeholder="Email"
        />

        <InputRegistationForms
            v-model="form.password"
            type="password"
            placeholder="Пароль"
        />

        <PrimaryButton :disabled="isLoading">
            <LoaderButtonSpinner v-if="isLoading" />
            <span v-else>Войти</span>
        </PrimaryButton>

        <FormErrorMessage :show="isError" />
    </form>

    <p class="auth-link">
        Нет аккаунта?
        <router-link to="/registration">Зарегистрироваться</router-link>
    </p>
</template>

<style scoped>
.auth-form {
    margin-top: 32px;
    max-width: 420px;
    display: flex;
    flex-direction: column;
    gap: 16px;
}
</style>
