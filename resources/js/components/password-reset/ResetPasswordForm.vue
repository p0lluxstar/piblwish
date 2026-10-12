<script setup lang="ts">
import { reactive, ref } from 'vue';

import BackOnMainPage from '@/components/ui/BackOnMainPage.vue';
import FormErrorMessage from '@/components/ui/FormErrorMessage.vue';
import InputRegistationForms from '@/components/ui/InputRegistationForms.vue';
import LoaderButtonSpinner from '@/components/ui/LoaderButtonSpinner.vue';
import PrimaryButton from '@/components/ui/PrimaryButton.vue';
import { useForgotPassword, useResetPassword } from '@/composables/useAuth';
import { useCodeTimer } from '@/composables/useCodeTimer';

const props = defineProps<{
    email: string;
}>();

const emit = defineEmits<{
    success: [];
    changeEmail: [];
}>();

const form = reactive({
    code: '',
    password: '',
    passwordConfirmation: '',
});

// Одна кнопка-глаз открывает и пароль, и подтверждение, чтобы их было удобно сверить
const isPasswordVisible = ref(false);

const resetPasswordMutation = useResetPassword();
const {
    mutateAsync: resetPassword,
    isPending,
    isError,
    errorMessage,
} = resetPasswordMutation;

const resendMutation = useForgotPassword();
const {
    isPending: isResendPending,
    isError: isResendError,
    errorMessage: resendErrorMessage,
} = resendMutation;

const { isExpired, formattedTime, restart: restartTimer } = useCodeTimer();

async function submitForm(): Promise<void> {
    resetPasswordMutation.reset();
    isPasswordVisible.value = false;

    if (isExpired.value) return;

    try {
        await resetPassword({
            email: props.email,
            code: form.code,
            password: form.password,
            password_confirmation: form.passwordConfirmation,
        });

        emit('success');
    } catch (error) {
        console.error(error);
    }
}

// Повторная отправка: новый код заменяет истёкший, поэтому поле кода
// очищается, прежняя ошибка снимается и отсчёт запускается заново.
// Введённый новый пароль сохраняется
async function resendCode(): Promise<void> {
    if (isResendPending.value) return;

    resendMutation.reset();

    try {
        await resendMutation.mutateAsync({ email: props.email });
    } catch (error) {
        console.error(error);
        return;
    }

    resetPasswordMutation.reset();
    form.code = '';
    restartTimer();
}
</script>

<template>
    <div class="header">
        <BackOnMainPage />

        <p class="eyebrow">Восстановление пароля</p>
    </div>

    <h2>Введите код и новый пароль</h2>

    <p class="hint">
        Если аккаунт с адресом
        <strong>{{ email }}</strong>
        существует, на него отправлен код.
    </p>

    <form class="auth-form" @submit.prevent="submitForm">
        <!-- После истечения срока код не принимается, поэтому форма
             скрывается до отправки нового кода -->
        <template v-if="!isExpired">
            <InputRegistationForms
                v-model="form.code"
                autocomplete="one-time-code"
                placeholder="Код из письма"
            />

            <p class="timer-text">Код действует: {{ formattedTime }}</p>

            <InputRegistationForms
                v-model="form.password"
                v-model:revealed="isPasswordVisible"
                type="password"
                autocomplete="new-password"
                placeholder="Новый пароль"
            />

            <InputRegistationForms
                v-model="form.passwordConfirmation"
                v-model:revealed="isPasswordVisible"
                type="password"
                :toggle="false"
                autocomplete="new-password"
                placeholder="Подтверждение пароля"
            />

            <PrimaryButton :disabled="isPending">
                <LoaderButtonSpinner v-if="isPending" />
                <span v-else>Сменить пароль</span>
            </PrimaryButton>

            <FormErrorMessage
                :show="isError"
                :message="errorMessage ?? undefined"
            />
        </template>

        <template v-else>
            <p class="expired-text">
                Срок действия кода истёк. Запросите новый код, он придёт на
                {{ email }}.
            </p>

            <PrimaryButton
                type="button"
                :disabled="isResendPending"
                @click="resendCode"
            >
                <LoaderButtonSpinner v-if="isResendPending" />
                <span v-else>Отправить новый код</span>
            </PrimaryButton>

            <FormErrorMessage
                :show="isResendError"
                :message="resendErrorMessage ?? undefined"
            />
        </template>
    </form>

    <p class="auth-link">
        <a href="#" @click.prevent="emit('changeEmail')">
            Указать другой email
        </a>
    </p>
</template>

<style scoped>
.hint {
    max-width: 420px;
    margin-top: 12px;
    color: var(--muted, var(--ink-muted));
    line-height: 1.5;
}

.auth-form {
    position: relative;
    margin-top: 32px;
    max-width: 420px;
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.expired-text {
    margin: 0;
    color: var(--muted, var(--ink-muted));
    font-size: 14px;
    line-height: 1.4;
    text-align: center;
}

.timer-text {
    position: absolute;
    max-width: 420px;
    top: -20px;
    right: 10px;
    color: var(--brand-violet);
    font-weight: 600;
    font-size: 13px;
    text-align: right;
}
</style>
