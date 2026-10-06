<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

import FormErrorMessage from '@/components/ui/FormErrorMessage.vue';
import LoaderButtonSpinner from '@/components/ui/LoaderButtonSpinner.vue';

const props = defineProps<{
    isPending?: boolean;
    isDeletingAccount?: boolean;
    passwordErrorMessage?: string | null;
    isPasswordChanged?: boolean;
}>();

const emit = defineEmits<{
    close: [];
    changePassword: [
        payload: {
            currentPassword: string;
            newPassword: string;
            newPasswordConfirmation: string;
        },
    ];
    deleteAccount: [];
}>();

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

const form = ref(defaultForm());

// Кнопка «Сохранить» активна, только когда заполнены все три поля пароля
const isPasswordFormFilled = computed(() =>
    Object.values(form.value).every((value) => value !== ''),
);

const handleSubmit = (): void => {
    if (!isPasswordFormFilled.value) return;

    emit('changePassword', { ...form.value });
};

// После успешной смены пароля очищаем поля формы
watch(
    () => props.isPasswordChanged,
    (isChanged) => {
        if (isChanged) {
            form.value = defaultForm();
        }
    },
);

// Показ подтверждения удаления аккаунта вместо кнопки «Удалить аккаунт»
const isDeleteConfirmVisible = ref(false);

const showDeleteConfirm = (): void => {
    isDeleteConfirmVisible.value = true;
};

const hideDeleteConfirm = (): void => {
    isDeleteConfirmVisible.value = false;
};

const handleDeleteAccount = (): void => {
    emit('deleteAccount');
};

const disableBodyScroll = (): void => {
    document.body.classList.add('modal-open');
};

const enableBodyScroll = (): void => {
    document.body.classList.remove('modal-open');
};

onMounted(() => {
    disableBodyScroll();
});

onUnmounted(() => {
    enableBodyScroll();
});

const closeModal = (): void => {
    form.value = defaultForm();
    hideDeleteConfirm();
    enableBodyScroll();
    emit('close');
};
</script>

<template>
    <Teleport to="body">
        <div class="modal-overlay" @click.self="closeModal">
            <div class="modal">
                <div class="modal-header">
                    <h2>Настройки аккаунта</h2>

                    <button class="close-btn" @click="closeModal"></button>
                </div>

                <form class="password-form" @submit.prevent="handleSubmit">
                    <h3 class="section-title">Смена пароля</h3>

                    <div class="form-row">
                        <input
                            v-model="form.currentPassword"
                            type="password"
                            autocomplete="current-password"
                            required
                            placeholder="Текущий пароль"
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
                        :show="!!props.passwordErrorMessage"
                        :message="props.passwordErrorMessage ?? undefined"
                    />

                    <p
                        v-if="props.isPasswordChanged"
                        class="form-message success-message"
                    >
                        Пароль изменён
                    </p>

                    <button
                        type="submit"
                        :disabled="props.isPending || !isPasswordFormFilled"
                        class="create-btn"
                    >
                        <LoaderButtonSpinner
                            v-if="props.isPending"
                            :size="18"
                        />

                        <span v-else>Сохранить</span>
                    </button>
                </form>

                <div class="danger-zone">
                    <button
                        v-if="!isDeleteConfirmVisible"
                        type="button"
                        class="delete-account-btn"
                        @click="showDeleteConfirm"
                    >
                        Удалить аккаунт
                    </button>

                    <div v-else class="delete-confirm">
                        <p class="delete-confirm-text">
                            Удалить аккаунт и все ваши списки?
                        </p>

                        <p class="warning-text">Это действие необратимо.</p>

                        <div class="delete-confirm-actions">
                            <button
                                type="button"
                                class="cancel-btn"
                                :disabled="props.isDeletingAccount"
                                @click="hideDeleteConfirm"
                            >
                                Отмена
                            </button>

                            <button
                                type="button"
                                class="delete-btn"
                                :disabled="props.isDeletingAccount"
                                @click="handleDeleteAccount"
                            >
                                <LoaderButtonSpinner
                                    v-if="props.isDeletingAccount"
                                    :size="18"
                                />

                                <span v-else>Удалить</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<style scoped lang="scss">
@use '../../../scss/ui/wishlistModal.scss';

.modal {
    max-width: 360px;
    padding: 24px;
}

.section-title {
    font-size: 13px;
    font-weight: 700;
    color: var(--ink, #241533);
    text-transform: uppercase;
    letter-spacing: 0.06em;
    margin: 0 0 12px;
}

.password-form {
    .form-row {
        display: flex;
        flex-direction: column;
        gap: 10px;
        margin-bottom: 14px;
    }

    input {
        padding: 11px 14px;
        border: 1.5px solid rgba(139, 92, 246, 0.15);
        border-radius: 14px;
        width: 100%;
        font-size: 13px;
        font-family: inherit;
        background: #faf8ff;
        transition: all 0.18s ease;

        &:focus {
            outline: none;
            border-color: #8b5cf6;
            background: #fff;
            box-shadow: 0 0 0 4px rgba(139, 92, 246, 0.12);
        }
    }
}

.form-message {
    margin: 0 0 12px;
    font-size: 13px;
}

.success-message {
    color: #16a34a;
    line-height: 1.4;
    text-align: center;
}

.danger-zone {
    margin-top: 20px;
    padding-top: 18px;
    border-top: 1px dashed rgba(139, 92, 246, 0.2);
}

.delete-account-btn {
    width: 100%;
    padding: 11px;
    border: 1.5px solid rgba(225, 29, 72, 0.25);
    border-radius: 18px;
    background: transparent;
    color: #e11d48;
    font-size: 13px;
    font-weight: 600;
    font-family: inherit;
    cursor: pointer;
    transition: all 0.2s ease;
}
.delete-account-btn:hover {
    background: #e11d48;
    color: #fff;
    border-color: transparent;
}

.delete-confirm {
    color: var(--ink, #241533);
    font-size: 13px;
    line-height: 1.5;

    p {
        margin: 0 0 6px;
    }

    .warning-text {
        font-size: 12px;
        font-weight: 600;
        color: #ec4899;
        margin-bottom: 14px;
    }
}

.delete-confirm-text {
    font-weight: 600;
}

.delete-confirm-actions {
    display: flex;
    gap: 12px;
}

.cancel-btn {
    display: flex;
    justify-content: center;
    align-items: center;
    background: rgba(139, 92, 246, 0.08);
    border: none;
    color: var(--ink, #241533);
    border-radius: 18px;
    font-size: 13px;
    font-weight: 600;
    font-family: inherit;
    flex: 1;
    padding: 11px;
    cursor: pointer;
    transition: all 0.2s ease;

    &:hover:not(:disabled) {
        background: rgba(139, 92, 246, 0.14);
    }

    &:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }
}

.delete-btn {
    display: flex;
    justify-content: center;
    align-items: center;
    background: linear-gradient(135deg, #fb7185, #ec4899);
    border: none;
    color: #fff;
    border-radius: 18px;
    font-size: 13px;
    font-weight: 700;
    font-family: inherit;
    flex: 1;
    padding: 11px;
    cursor: pointer;
    transition: all 0.2s ease;
    box-shadow: 0 14px 30px -12px rgba(236, 72, 153, 0.5);

    &:hover:not(:disabled) {
        transform: translateY(-2px);
        box-shadow: 0 18px 36px -12px rgba(236, 72, 153, 0.55);
    }

    &:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
    }
}
</style>
