<script setup lang="ts">
import { Check } from '@lucide/vue';
import { reactive, ref } from 'vue';
import { useRoute } from 'vue-router';

import BackOnMainPage from '@/components/ui/BackOnMainPage.vue';
import FormErrorMessage from '@/components/ui/FormErrorMessage.vue';
import InputRegistationForms from '@/components/ui/InputRegistationForms.vue';
import LoaderButtonSpinner from '@/components/ui/LoaderButtonSpinner.vue';
import PrimaryButton from '@/components/ui/PrimaryButton.vue';
import { useLogin } from '@/composables/useAuth';

const form = reactive({
    email: '',
    password: '',
    remember: true,
});

const isPasswordVisible = ref(false);

// Перехватчик 401 переводит сюда с ?expired=1, когда сессия истекла
const route = useRoute();
const isSessionExpired = route.query.expired === '1';

const { mutateAsync: login, isPending, isError, errorMessage } = useLogin();

async function submitForm(): Promise<void> {
    // Открытый пароль не остаётся на экране после отправки
    isPasswordVisible.value = false;

    try {
        await login({
            email: form.email,
            password: form.password,
            remember: form.remember,
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

    <p v-if="isSessionExpired" class="session-expired">
        Сессия истекла. Войдите снова, чтобы продолжить.
    </p>

    <form class="auth-form" @submit.prevent="submitForm">
        <InputRegistationForms
            v-model="form.email"
            type="email"
            autocomplete="email"
            placeholder="Email"
        />

        <InputRegistationForms
            v-model="form.password"
            v-model:revealed="isPasswordVisible"
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

        <FormErrorMessage :show="isError" :message="errorMessage" />
    </form>

    <p class="auth-link">
        <router-link to="/forgot-password">Забыли пароль?</router-link>
    </p>

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

.session-expired {
    max-width: 420px;
    margin: 16px 0 0;
    color: #d14343;
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
    border-color: var(--brand-violet, #8b5cf6);
    transform: scale(1.08);
}

.remember-input:checked + .remember-box {
    border-color: transparent;
    background: var(--brand-violet, #8b5cf6);
}

.remember-input:focus-visible + .remember-box {
    outline: 2px solid var(--brand-violet, #8b5cf6);
    outline-offset: 2px;
}
</style>
