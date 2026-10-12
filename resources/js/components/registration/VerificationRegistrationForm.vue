<script setup lang="ts">
import { reactive } from 'vue';

import FormErrorMessage from '@/components/ui/FormErrorMessage.vue';
import InputRegistationForms from '@/components/ui/InputRegistationForms.vue';
import LoaderButtonSpinner from '@/components/ui/LoaderButtonSpinner.vue';
import PrimaryButton from '@/components/ui/PrimaryButton.vue';
import {
    useResendVerificationCode,
    useVerifyRegistration,
} from '@/composables/useAuth';
import { useCodeTimer } from '@/composables/useCodeTimer';
import { useFormErrors } from '@/composables/useFormErrors';
import { validateVerificationCode } from '@/lib/authValidation';

const props = defineProps<{
    email: string;
}>();

const emit = defineEmits<{
    success: [];
}>();

const form = reactive({
    code: '',
});

const { isExpired, formattedTime, restart: restartTimer } = useCodeTimer();

const VerifyRegistrationMutation = useVerifyRegistration();
const {
    mutateAsync: verifyRegistration,
    isPending,
    errorMessage,
} = VerifyRegistrationMutation;

const { invalidField, message, validate } = useFormErrors({
    form,
    serverError: VerifyRegistrationMutation.error,
    serverMessage: errorMessage,
    resetServerError: VerifyRegistrationMutation.reset,
});

async function submitForm(): Promise<void> {
    VerifyRegistrationMutation.reset();

    if (isExpired.value) return; // защита

    // С ошибкой проверки на клиенте запрос на сервер не отправляется
    if (!validate(validateVerificationCode(form.code))) return;

    try {
        await verifyRegistration({
            email: props.email,
            code: form.code,
        });

        emit('success');
    } catch (error) {
        console.error(error);
    }
}

const resendMutation = useResendVerificationCode();
const { isPending: isResending, errorMessage: resendErrorMessage } =
    resendMutation;

// Повторная отправка: новый код заменяет истёкший, поэтому поле
// очищается, прежние ошибки снимаются и таймер запускается заново
async function resendCode(): Promise<void> {
    resendMutation.reset();

    try {
        await resendMutation.mutateAsync({ email: props.email });
    } catch (error) {
        console.error(error);
        return;
    }

    VerifyRegistrationMutation.reset();
    validate(null);
    form.code = '';
    restartTimer();
}
</script>

<template>
    <div class="header">
        <p class="eyebrow">Подтверждение регистрации</p>
    </div>

    <h2>Введите код подтверждения</h2>
    <form class="verification-form" novalidate @submit.prevent="submitForm">
        <!-- После истечения срока код не принимается, поэтому поле
             скрывается до отправки нового кода -->
        <template v-if="!isExpired">
            <InputRegistationForms
                v-model="form.code"
                :invalid="invalidField === 'code'"
                placeholder="Введите код"
            />

            <p class="timer-text">Код действует: {{ formattedTime }}</p>

            <PrimaryButton :disabled="isPending">
                <LoaderButtonSpinner v-if="isPending" />
                <span v-else>Подтвердить</span>
            </PrimaryButton>

            <FormErrorMessage
                :show="Boolean(message)"
                :message="message ?? undefined"
            />
        </template>

        <template v-else>
            <p class="expired-text">
                Срок действия кода истёк. Запросите новый код, он придёт на
                {{ email }}.
            </p>

            <PrimaryButton
                type="button"
                :disabled="isResending"
                @click="resendCode"
            >
                <LoaderButtonSpinner v-if="isResending" />
                <span v-else>Отправить новый код</span>
            </PrimaryButton>

            <FormErrorMessage
                :show="Boolean(resendErrorMessage)"
                :message="resendErrorMessage ?? undefined"
            />
        </template>
    </form>
</template>

<style scoped>
.verification-form {
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
