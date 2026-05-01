<script setup lang="ts">
import { useMutation } from '@tanstack/vue-query';
import { reactive } from 'vue';

import FormErrorMessage from '@/components/ui/FormErrorMessage.vue';
import LoaderButtonSpinner from '@/components/ui/LoaderButtonSpinner.vue';
import MainInputForm from '@/components/ui/MainInputForm.vue';
import PrimaryButton from '@/components/ui/PrimaryButton.vue';
import { api } from '@/lib/api';

type RegisterPayload = {
    username: string;
    email: string;
    password: string;
    password_confirmation: string;
};

const emit = defineEmits<{
    success: [email: string];
}>();

const form = reactive({
    login: '',
    email: '',
    password: '',
    passwordConfirmation: '',
});

const registerMutation = useMutation({
    mutationFn: (payload: RegisterPayload) => api.post('/v1/register', payload),
    onSuccess: (_, variables) => {
        emit('success', variables.email);
    },
});

const isLoading = registerMutation.isPending;
const isError = registerMutation.isError;

async function submitForm(): Promise<void> {
    // Сбрасываем прошлую ошибку и статус mutation перед новой отправкой формы
    registerMutation.reset();

    try {
        await registerMutation.mutateAsync({
            username: form.login,
            email: form.email,
            password: form.password,
            password_confirmation: form.passwordConfirmation,
        });
    } catch (error) {
        console.error(error);
    }
}
</script>

<template>
    <form class="registration-form" @submit.prevent="submitForm">
        <MainInputForm v-model="form.login" placeholder="Логин" />

        <MainInputForm v-model="form.email" type="email" placeholder="Email" />

        <MainInputForm
            v-model="form.password"
            type="password"
            placeholder="Пароль"
        />

        <MainInputForm
            v-model="form.passwordConfirmation"
            type="password"
            placeholder="Подтверждение пароля"
        />

        <PrimaryButton :disabled="isLoading">
            <LoaderButtonSpinner v-if="isLoading" />
            <span v-else>Продолжить</span>
        </PrimaryButton>

        <FormErrorMessage :show="isError" />
    </form>
</template>

<style scoped>
.registration-form {
    margin-top: 32px;
    max-width: 420px;
    display: flex;
    flex-direction: column;
    gap: 16px;
}
</style>
