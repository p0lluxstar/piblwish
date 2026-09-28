<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue';

import LoaderButtonSpinner from '@/components/ui/LoaderButtonSpinner.vue';

const props = defineProps<{
    isPending?: boolean;
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

const defaultForm = () => ({
    currentPassword: '',
    newPassword: '',
    newPasswordConfirmation: '',
});

const form = ref(defaultForm());

const handleSubmit = (): void => {
    emit('changePassword', { ...form.value });
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
    enableBodyScroll();
    emit('close');
};
</script>

<template>
    <div class="modal-overlay" @click.self="closeModal">
        <div class="modal">
            <div class="modal-header">
                <h2>Настройки аккаунта</h2>

                <button class="close-btn" @click="closeModal">×</button>
            </div>

            <form class="password-form" @submit.prevent="handleSubmit">
                <h3 class="section-title">Смена пароля</h3>

                <div class="form-row">
                    <input
                        v-model="form.currentPassword"
                        type="password"
                        autocomplete="current-password"
                        placeholder="Текущий пароль"
                    />

                    <input
                        v-model="form.newPassword"
                        type="password"
                        autocomplete="new-password"
                        placeholder="Новый пароль"
                    />

                    <input
                        v-model="form.newPasswordConfirmation"
                        type="password"
                        autocomplete="new-password"
                        placeholder="Повторите новый пароль"
                    />
                </div>

                <button
                    type="submit"
                    :disabled="props.isPending"
                    class="create-btn"
                >
                    <LoaderButtonSpinner v-if="props.isPending" :size="18" />

                    <span v-else>Сохранить</span>
                </button>
            </form>

            <div class="danger-zone">
                <button
                    type="button"
                    class="delete-account-btn"
                    @click="handleDeleteAccount"
                >
                    Удалить аккаунт
                </button>
            </div>
        </div>
    </div>
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
</style>
