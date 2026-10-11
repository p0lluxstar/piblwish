<script setup lang="ts">
import { computed, onUnmounted, ref } from 'vue';

import FormErrorMessage from '@/components/ui/FormErrorMessage.vue';
import LoaderButtonSpinner from '@/components/ui/LoaderButtonSpinner.vue';
import PasswordToggle from '@/components/ui/PasswordToggle.vue';
import {
    useConfirmEmailChange,
    useRequestEmailChange,
} from '@/composables/useAuth';
import { useAuthStore } from '@/stores/auth';

// Пауза перед повторной отправкой кода, секунд
const RESEND_DELAY_SECONDS = 60;

// Срок действия кода; совпадает с EMAIL_CHANGE_CODE_TTL_MINUTES в UserService
const CODE_TTL_MINUTES = 15;

const auth = useAuthStore();

const {
    mutate: requestEmailChange,
    isPending: isRequesting,
    errorMessage: requestErrorMessage,
    reset: resetRequest,
} = useRequestEmailChange();

const {
    mutate: confirmEmailChange,
    isPending: isConfirming,
    errorMessage: confirmErrorMessage,
    reset: resetConfirm,
} = useConfirmEmailChange();

// idle — показан текущий адрес, form — новый адрес и пароль, code — ввод кода
const step = ref<'idle' | 'form' | 'code'>('idle');

const newEmail = ref('');
const currentPassword = ref('');
const isPasswordVisible = ref(false);
const code = ref('');
const isChanged = ref(false);

const isFormFilled = computed(
    () => newEmail.value.trim() !== '' && currentPassword.value !== '',
);
const isCodeFilled = computed(() => /^\d{6}$/.test(code.value));

// На шаге ввода кода ошибка повторной отправки показывается там же
const errorMessage = computed(
    (): string | null => confirmErrorMessage.value ?? requestErrorMessage.value,
);

const resendSecondsLeft = ref(0);
let resendTimer: number | null = null;

const stopResendTimer = (): void => {
    if (resendTimer) {
        window.clearInterval(resendTimer);
        resendTimer = null;
    }
};

const startResendTimer = (): void => {
    stopResendTimer();
    resendSecondsLeft.value = RESEND_DELAY_SECONDS;

    resendTimer = window.setInterval(() => {
        resendSecondsLeft.value -= 1;

        if (resendSecondsLeft.value <= 0) {
            stopResendTimer();
        }
    }, 1000);
};

onUnmounted(stopResendTimer);

const openForm = (): void => {
    isChanged.value = false;
    step.value = 'form';
};

const cancel = (): void => {
    stopResendTimer();
    resetRequest();
    resetConfirm();
    newEmail.value = '';
    currentPassword.value = '';
    isPasswordVisible.value = false;
    code.value = '';
    step.value = 'idle';
};

// Пароль хранится до конца смены: он нужен для повторной отправки кода
const sendCode = (): void => {
    if (!isFormFilled.value) return;

    // Открытый пароль не остаётся на экране после отправки
    isPasswordVisible.value = false;
    resetConfirm();

    requestEmailChange(
        {
            email: newEmail.value.trim(),
            current_password: currentPassword.value,
        },
        {
            onSuccess: () => {
                code.value = '';
                step.value = 'code';
                startResendTimer();
            },
        },
    );
};

const resendCode = (): void => {
    if (resendSecondsLeft.value > 0) return;

    sendCode();
};

const confirm = (): void => {
    if (!isCodeFilled.value) return;

    resetRequest();

    confirmEmailChange(
        { code: code.value },
        {
            onSuccess: () => {
                cancel();
                isChanged.value = true;
            },
        },
    );
};
</script>

<template>
    <section class="email-section">
        <h3 class="section-title">Email</h3>

        <div v-if="step === 'idle'" class="account-current">
            <span class="account-value">{{ auth.user?.email }}</span>

            <button type="button" class="change-btn" @click="openForm">
                Изменить
            </button>
        </div>

        <p
            v-if="step === 'idle' && isChanged"
            class="form-message success-message"
        >
            Email изменён
        </p>

        <form
            v-if="step === 'form'"
            class="account-form"
            @submit.prevent="sendCode"
        >
            <div class="form-row">
                <input
                    v-model="newEmail"
                    type="email"
                    autocomplete="email"
                    placeholder="Новый email"
                    required
                />

                <div class="password-field">
                    <input
                        v-model="currentPassword"
                        :type="isPasswordVisible ? 'text' : 'password'"
                        autocomplete="current-password"
                        placeholder="Текущий пароль"
                        required
                    />

                    <PasswordToggle v-model="isPasswordVisible" :size="18" />
                </div>
            </div>

            <FormErrorMessage
                class="form-message"
                :show="!!requestErrorMessage"
                :message="requestErrorMessage ?? undefined"
            />

            <div class="form-actions">
                <button
                    type="button"
                    class="cancel-btn"
                    :disabled="isRequesting"
                    @click="cancel"
                >
                    Отмена
                </button>

                <button
                    type="submit"
                    class="create-btn"
                    :disabled="isRequesting || !isFormFilled"
                >
                    <LoaderButtonSpinner v-if="isRequesting" :size="18" />

                    <span v-else>Получить код</span>
                </button>
            </div>
        </form>

        <form
            v-if="step === 'code'"
            class="account-form"
            @submit.prevent="confirm"
        >
            <p class="code-hint">
                Код отправлен на
                <strong>{{ newEmail.trim() }}</strong>
                . Он действует {{ CODE_TTL_MINUTES }} минут.
            </p>

            <div class="form-row">
                <input
                    v-model="code"
                    type="text"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    maxlength="6"
                    placeholder="Код из письма"
                    required
                />
            </div>

            <FormErrorMessage
                class="form-message"
                :show="!!errorMessage"
                :message="errorMessage ?? undefined"
            />

            <button
                type="button"
                class="resend-btn"
                :disabled="resendSecondsLeft > 0 || isRequesting"
                @click="resendCode"
            >
                <template v-if="resendSecondsLeft > 0">
                    Отправить код ещё раз через {{ resendSecondsLeft }} с
                </template>

                <template v-else>Отправить код ещё раз</template>
            </button>

            <div class="form-actions">
                <button
                    type="button"
                    class="cancel-btn"
                    :disabled="isConfirming"
                    @click="cancel"
                >
                    Отмена
                </button>

                <button
                    type="submit"
                    class="create-btn"
                    :disabled="isConfirming || !isCodeFilled"
                >
                    <LoaderButtonSpinner v-if="isConfirming" :size="18" />

                    <span v-else>Подтвердить</span>
                </button>
            </div>
        </form>
    </section>
</template>

<style scoped lang="scss">
@use '../../../scss/ui/createButton.scss';
@use '../../../scss/ui/accountSection.scss';

.email-section {
    margin-bottom: 20px;
    padding-bottom: 18px;
    border-bottom: 1px dashed rgba(139, 92, 246, 0.2);
}

.code-hint {
    margin: 0 0 12px;
    font-size: 13px;
    line-height: 1.5;
    color: var(--ink, #241533);
    overflow-wrap: anywhere;
}

.resend-btn {
    display: block;
    margin: 0 auto 14px;
    padding: 0;
    border: none;
    background: none;
    color: #8b5cf6;
    font-size: 12px;
    font-weight: 600;
    font-family: inherit;
    cursor: pointer;

    &:disabled {
        color: rgba(36, 21, 51, 0.45);
        cursor: default;
    }
}
</style>
