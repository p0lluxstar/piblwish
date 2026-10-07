<script setup lang="ts">
import { computed, ref } from 'vue';

import FormErrorMessage from '@/components/ui/FormErrorMessage.vue';
import LoaderButtonSpinner from '@/components/ui/LoaderButtonSpinner.vue';
import { useChangePassword } from '@/composables/useAuth';

const {
    mutate: changePassword,
    isPending,
    errorMessage,
    reset,
} = useChangePassword();

type PasswordForm = {
    currentPassword: string;
    newPassword: string;
    newPasswordConfirmation: string;
};

const defaultForm = (): PasswordForm => ({
    currentPassword: '',
    newPassword: '',
    newPasswordConfirmation: '',
});

// Форма показывается по кнопке «Изменить», как в разделе Email
const isFormVisible = ref(false);
const isChanged = ref(false);
const form = ref(defaultForm());

// Кнопка «Сменить пароль» активна, только когда заполнены все три поля
const isFormFilled = computed(() =>
    Object.values(form.value).every((value) => value !== ''),
);

const openForm = (): void => {
    isChanged.value = false;
    isFormVisible.value = true;
};

const cancel = (): void => {
    reset();
    form.value = defaultForm();
    isFormVisible.value = false;
};

const submit = (): void => {
    if (!isFormFilled.value) return;

    changePassword(
        {
            current_password: form.value.currentPassword,
            password: form.value.newPassword,
            password_confirmation: form.value.newPasswordConfirmation,
        },
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
    <section>
        <h3 class="section-title">Пароль</h3>

        <div v-if="!isFormVisible" class="account-current">
            <span
                class="account-value password-value"
                aria-label="Пароль скрыт"
            >
                ••••••••
            </span>

            <button type="button" class="change-btn" @click="openForm">
                Изменить
            </button>
        </div>

        <p
            v-if="!isFormVisible && isChanged"
            class="form-message success-message"
        >
            Пароль изменён
        </p>

        <form
            v-if="isFormVisible"
            class="account-form"
            @submit.prevent="submit"
        >
            <div class="form-row">
                <input
                    v-model="form.currentPassword"
                    type="password"
                    autocomplete="current-password"
                    placeholder="Текущий пароль"
                    required
                />

                <input
                    v-model="form.newPassword"
                    type="password"
                    autocomplete="new-password"
                    placeholder="Новый пароль"
                    required
                />

                <input
                    v-model="form.newPasswordConfirmation"
                    type="password"
                    autocomplete="new-password"
                    placeholder="Повторите новый пароль"
                    required
                />
            </div>

            <FormErrorMessage
                class="form-message"
                :show="!!errorMessage"
                :message="errorMessage ?? undefined"
            />

            <div class="form-actions">
                <button
                    type="button"
                    class="cancel-btn"
                    :disabled="isPending"
                    @click="cancel"
                >
                    Отмена
                </button>

                <button
                    type="submit"
                    class="create-btn"
                    :disabled="isPending || !isFormFilled"
                >
                    <LoaderButtonSpinner v-if="isPending" :size="18" />

                    <span v-else>Сменить пароль</span>
                </button>
            </div>
        </form>
    </section>
</template>

<style scoped lang="scss">
@use '../../../scss/ui/createButton.scss';
@use '../../../scss/ui/accountSection.scss';

.password-value {
    letter-spacing: 0.1em;
}
</style>
