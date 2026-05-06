<script setup lang="ts">
import { reactive } from 'vue';

import BackOnMainPage from '@/components/ui/BackOnMainPage.vue';
import FormErrorMessage from '@/components/ui/FormErrorMessage.vue';
import InputRegistationForms from '@/components/ui/InputRegistationForms.vue';
import LoaderButtonSpinner from '@/components/ui/LoaderButtonSpinner.vue';
import PrimaryButton from '@/components/ui/PrimaryButton.vue';
import { useRegister } from '@/composables/useAuth';

const emit = defineEmits<{
    success: [email: string];
}>();

const form = reactive({
    login: '',
    email: '',
    password: '',
    passwordConfirmation: '',
});

const registerMutation = useRegister();
const { mutateAsync: register, isPending, isError } = registerMutation;

async function submitForm(): Promise<void> {
    // Сбрасываем прошлую ошибку и статус mutation перед новой отправкой формы
    registerMutation.reset();

    try {
        await register({
            username: form.login,
            email: form.email,
            password: form.password,
            password_confirmation: form.passwordConfirmation,
        });

        emit?.('success', form.email);
    } catch (error) {
        console.error(error);
    }
}
</script>

<template>
    <div class="header">
        <BackOnMainPage />
        <p class="eyebrow">Регистрация</p>
    </div>

    <h2>Создать аккаунт</h2>
    <form class="registration-form" @submit.prevent="submitForm">
        <InputRegistationForms v-model="form.login" placeholder="Логин" />

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

        <InputRegistationForms
            v-model="form.passwordConfirmation"
            type="password"
            placeholder="Подтверждение пароля"
        />

        <PrimaryButton :disabled="isPending">
            <LoaderButtonSpinner v-if="isPending" />
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
