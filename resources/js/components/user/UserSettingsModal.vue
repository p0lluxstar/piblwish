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
    max-width: 340px;
    padding: 20px;
}

.section-title {
    font-size: 13px;
    font-weight: 600;
    color: #3b2146;
    margin: 0 0 10px;
}

.password-form {
    .form-row {
        display: flex;
        flex-direction: column;
        gap: 8px;
        margin-bottom: 12px;
    }

    input {
        padding: 9px 12px;
        border: 1px solid #ddd;
        border-radius: 10px;
        width: 100%;
        font-size: 13px;
        font-family: inherit;
    }
}

.danger-zone {
    margin-top: 16px;
    padding-top: 16px;
    border-top: 1px solid rgba(226, 195, 211, 0.45);
}

.delete-account-btn {
    width: 100%;
    padding: 10px;
    border: 1px solid rgba(209, 67, 67, 0.35);
    border-radius: 18px;
    background: transparent;
    color: #d14343;
    font-size: 12px;
    font-weight: 500;
    font-family: 'DM Sans', sans-serif;
    cursor: pointer;
    transition: all 0.18s;
}
.delete-account-btn:hover {
    background: rgba(209, 67, 67, 0.08);
}
</style>
