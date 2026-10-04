<script setup lang="ts">
import { reactive } from 'vue';

import BackOnMainPage from '@/components/ui/BackOnMainPage.vue';
import FormErrorMessage from '@/components/ui/FormErrorMessage.vue';
import InputRegistationForms from '@/components/ui/InputRegistationForms.vue';
import LoaderButtonSpinner from '@/components/ui/LoaderButtonSpinner.vue';
import PrimaryButton from '@/components/ui/PrimaryButton.vue';
import { useForgotPassword } from '@/composables/useAuth';

const props = defineProps<{
    initialEmail?: string;
}>();

const emit = defineEmits<{
    success: [email: string];
}>();

const form = reactive({
    email: props.initialEmail ?? '',
});

const forgotPasswordMutation = useForgotPassword();
const {
    mutateAsync: forgotPassword,
    isPending,
    isError,
    errorMessage,
} = forgotPasswordMutation;

async function submitForm(): Promise<void> {
    forgotPasswordMutation.reset();

    try {
        await forgotPassword({ email: form.email });

        emit('success', form.email);
    } catch (error) {
        console.error(error);
    }
}
</script>

<template>
    <div class="header">
        <BackOnMainPage />

        <p class="eyebrow">Восстановление пароля</p>
    </div>

    <h2>Забыли пароль?</h2>

    <p class="hint">
        Укажите email, с которым вы регистрировались. Мы отправим на него код
        для смены пароля.
    </p>

    <form class="auth-form" @submit.prevent="submitForm">
        <InputRegistationForms
            v-model="form.email"
            type="email"
            placeholder="Email"
        />

        <PrimaryButton :disabled="isPending">
            <LoaderButtonSpinner v-if="isPending" />
            <span v-else>Получить код</span>
        </PrimaryButton>

        <FormErrorMessage
            :show="isError"
            :message="errorMessage ?? undefined"
        />
    </form>
</template>

<style scoped>
.hint {
    max-width: 420px;
    margin-top: 12px;
    color: var(--muted, #7a6278);
    line-height: 1.5;
}

.auth-form {
    margin-top: 32px;
    max-width: 420px;
    display: flex;
    flex-direction: column;
    gap: 16px;
}
</style>
