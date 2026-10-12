<script setup lang="ts">
import { Check } from '@lucide/vue';
import { reactive, ref } from 'vue';
import { useRoute } from 'vue-router';

import VerificationRegistrationForm from '@/components/registration/VerificationRegistrationForm.vue';
import BackOnMainPage from '@/components/ui/BackOnMainPage.vue';
import FormErrorMessage from '@/components/ui/FormErrorMessage.vue';
import InputRegistationForms from '@/components/ui/InputRegistationForms.vue';
import LoaderButtonSpinner from '@/components/ui/LoaderButtonSpinner.vue';
import PrimaryButton from '@/components/ui/PrimaryButton.vue';
import { useLogin } from '@/composables/useAuth';
import { useFormErrors } from '@/composables/useFormErrors';
import { validateLogin } from '@/lib/authValidation';
import type { LoginPayload } from '@/types/auth';

const form = reactive<LoginPayload>({
    email: '',
    password: '',
    remember: true,
});

const isPasswordVisible = ref(false);

// Email не подтверждён: сервер отправил новый код, и вместо формы входа
// показывается форма ввода кода
const isVerifying = ref(false);

// Перехватчик 401 переводит сюда с ?expired=1, когда сессия истекла
const route = useRoute();
const isSessionExpired = route.query.expired === '1';

const loginMutation = useLogin();
const { mutateAsync: login, isPending, errorMessage } = loginMutation;

const { invalidField, message, validate } = useFormErrors({
    form,
    serverError: loginMutation.error,
    serverMessage: errorMessage,
    resetServerError: loginMutation.reset,
});

async function submitForm(): Promise<void> {
    // Открытый пароль не остаётся на экране после отправки
    isPasswordVisible.value = false;
    // Убираем ошибку прошлой попытки входа, чтобы она не мешала новой
    loginMutation.reset();

    // С ошибкой проверки на клиенте запрос на сервер не отправляется
    if (!validate(validateLogin(form))) return;

    try {
        await login({ ...form });
    } catch (error) {
        // Вход отвечает 403 только неподтверждённому аккаунту с верным паролем
        if (loginMutation.error.value?.response?.status === 403) {
            loginMutation.reset();
            isVerifying.value = true;
            return;
        }

        console.error(error);
    }
}

// Email подтверждён: входим с теми же данными. Если вход не удался,
// ошибка показывается в форме входа
async function handleVerified(): Promise<void> {
    isVerifying.value = false;

    try {
        await login({ ...form });
    } catch (error) {
        console.error(error);
    }
}

function cancelVerification(): void {
    isVerifying.value = false;
}
</script>

<template>
    <template v-if="isVerifying">
        <VerificationRegistrationForm
            :email="form.email"
            @success="handleVerified"
        />

        <p class="auth-link">
            <a href="#" @click.prevent="cancelVerification">
                Вернуться ко входу
            </a>
        </p>
    </template>

    <template v-else>
        <div class="header">
            <BackOnMainPage />

            <p class="eyebrow">Вход</p>
        </div>

        <h2>Войдите в аккаунт</h2>

        <p v-if="isSessionExpired" class="session-expired">
            Сессия истекла. Войдите снова, чтобы продолжить.
        </p>

        <form class="auth-form" novalidate @submit.prevent="submitForm">
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
                autocomplete="current-password"
                placeholder="Пароль"
            />

            <label class="remember">
                <input
                    v-model="form.remember"
                    type="checkbox"
                    class="remember-input"
                />

                <!-- Форма как у отметки дела в карточке списка дел, цвет фирменный фиолетовый -->
                <span class="remember-box" aria-hidden="true">
                    <Check v-if="form.remember" :size="12" />
                </span>

                Запомнить меня
            </label>

            <PrimaryButton :disabled="isPending">
                <LoaderButtonSpinner v-if="isPending" />
                <span v-else>Войти</span>
            </PrimaryButton>

            <FormErrorMessage
                :show="Boolean(message)"
                :message="message ?? undefined"
            />
        </form>

        <p class="auth-link">
            <router-link to="/forgot-password">Забыли пароль?</router-link>
        </p>

        <p class="auth-link">
            Нет аккаунта?
            <router-link to="/registration">Зарегистрироваться</router-link>
        </p>
    </template>
</template>

<style scoped>
.auth-form {
    margin-top: 32px;
    max-width: 420px;
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.session-expired {
    max-width: 420px;
    margin: 16px 0 0;
    color: var(--danger);
    font-size: 14px;
    line-height: 1.4;
}

.remember {
    display: flex;
    align-items: center;
    gap: 8px;
    width: fit-content;
    font-size: 14px;
    cursor: pointer;
    user-select: none;
}

/* Настоящий чекбокс скрыт, но остаётся доступным с клавиатуры */
.remember-input {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}

.remember-box {
    display: grid;
    place-items: center;
    flex-shrink: 0;
    width: 19px;
    height: 19px;
    border-radius: 7px;
    border: 1.5px solid rgba(139, 92, 246, 0.45);
    color: #fff;
    transition: all 0.15s;
}

.remember:hover .remember-box {
    border-color: var(--brand-violet);
    transform: scale(1.08);
}

.remember-input:checked + .remember-box {
    border-color: transparent;
    background: var(--brand-violet);
}

.remember-input:focus-visible + .remember-box {
    outline: 2px solid var(--brand-violet);
    outline-offset: 2px;
}
</style>
