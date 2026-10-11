<script setup lang="ts">
import { onMounted, onUnmounted, reactive, ref } from 'vue';

import BackOnMainPage from '@/components/ui/BackOnMainPage.vue';
import FormErrorMessage from '@/components/ui/FormErrorMessage.vue';
import InputRegistationForms from '@/components/ui/InputRegistationForms.vue';
import LoaderButtonSpinner from '@/components/ui/LoaderButtonSpinner.vue';
import PrimaryButton from '@/components/ui/PrimaryButton.vue';
import { useForgotPassword, useResetPassword } from '@/composables/useAuth';

// Повторный запрос кода для одного email сервер принимает не чаще раза в минуту
const RESEND_COOLDOWN_SECONDS = 60;

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
const resendSent = ref(false);

// Обратный отсчёт до возможности повторной отправки кода
const resendIn = ref(0);
let timer: number | null = null;

function startCooldown(): void {
    stopCooldown();
    resendIn.value = RESEND_COOLDOWN_SECONDS;

    timer = window.setInterval(() => {
        resendIn.value--;

        if (resendIn.value <= 0) {
            stopCooldown();
        }
    }, 1000);
}

function stopCooldown(): void {
    if (timer) {
        window.clearInterval(timer);
        timer = null;
    }
}

onMounted(startCooldown);
onUnmounted(stopCooldown);

async function submitForm(): Promise<void> {
    resetPasswordMutation.reset();
    isPasswordVisible.value = false;

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

async function resendCode(): Promise<void> {
    if (isResendPending.value) return;

    resendMutation.reset();
    resendSent.value = false;

    try {
        await resendMutation.mutateAsync({ email: props.email });

        resendSent.value = true;
        startCooldown();
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

    <h2>Введите код и новый пароль</h2>

    <p class="hint">
        Если аккаунт с адресом
        <strong>{{ email }}</strong>
        существует, на него отправлен код. Код действует 10 минут.
    </p>

    <form class="auth-form" @submit.prevent="submitForm">
        <InputRegistationForms
            v-model="form.code"
            autocomplete="one-time-code"
            placeholder="Код из письма"
        />

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
    </form>

    <p class="auth-link">
        <span v-if="resendIn > 0">
            <template v-if="resendSent">Новый код отправлен.</template>
            Повторная отправка через {{ resendIn }} с
        </span>
        <a v-else href="#" @click.prevent="resendCode">Отправить код ещё раз</a>
    </p>

    <FormErrorMessage
        :show="isResendError"
        :message="resendErrorMessage ?? undefined"
    />

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
