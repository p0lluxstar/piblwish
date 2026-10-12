<script setup lang="ts">
import { reactive, ref } from 'vue';

import BackOnMainPage from '@/components/ui/BackOnMainPage.vue';
import FormErrorMessage from '@/components/ui/FormErrorMessage.vue';
import InputRegistationForms from '@/components/ui/InputRegistationForms.vue';
import LoaderButtonSpinner from '@/components/ui/LoaderButtonSpinner.vue';
import PrimaryButton from '@/components/ui/PrimaryButton.vue';
import { useRegister } from '@/composables/useAuth';
import { useFormErrors } from '@/composables/useFormErrors';
import { validateRegistration } from '@/lib/authValidation';
import type { RegisterPayload } from '@/types/auth';

const emit = defineEmits<{
    success: [email: string];
}>();

// Поля названы как в запросе, чтобы подсвечивать их и по ошибкам сервера
const form = reactive<RegisterPayload>({
    username: '',
    email: '',
    password: '',
    password_confirmation: '',
});

// Одна кнопка-глаз открывает и пароль, и подтверждение, чтобы их было удобно сверить
const isPasswordVisible = ref(false);

const registerMutation = useRegister();
const { mutateAsync: register, isPending, errorMessage } = registerMutation;

const { invalidField, message, validate } = useFormErrors({
    form,
    serverError: registerMutation.error,
    serverMessage: errorMessage,
    resetServerError: registerMutation.reset,
});

async function submitForm(): Promise<void> {
    // Сбрасываем прошлую ошибку и статус mutation перед новой отправкой формы
    registerMutation.reset();
    isPasswordVisible.value = false;

    // С ошибкой проверки на клиенте запрос на сервер не отправляется
    if (!validate(validateRegistration(form))) return;

    try {
        await register({ ...form });

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
    <form class="registration-form" novalidate @submit.prevent="submitForm">
        <InputRegistationForms
            v-model="form.username"
            :invalid="invalidField === 'username'"
            autocomplete="username"
            placeholder="Логин"
        />

        <InputRegistationForms
            v-model="form.email"
            :invalid="invalidField === 'email'"
            type="email"
            autocomplete="email"
            placeholder="Email"
        />

        <InputRegistationForms
            v-model="form.password"
            v-model:revealed="isPasswordVisible"
            :invalid="invalidField === 'password'"
            type="password"
            autocomplete="new-password"
            placeholder="Пароль"
        />

        <InputRegistationForms
            v-model="form.password_confirmation"
            v-model:revealed="isPasswordVisible"
            :invalid="invalidField === 'password_confirmation'"
            type="password"
            :toggle="false"
            autocomplete="new-password"
            placeholder="Подтверждение пароля"
        />

        <PrimaryButton :disabled="isPending">
            <LoaderButtonSpinner v-if="isPending" />
            <span v-else>Продолжить</span>
        </PrimaryButton>

        <FormErrorMessage
            :show="Boolean(message)"
            :message="message ?? undefined"
        />
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
