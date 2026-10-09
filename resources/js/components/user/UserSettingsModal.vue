<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';

import LoaderButtonSpinner from '@/components/ui/LoaderButtonSpinner.vue';
import AppBackgroundPicker from '@/components/user/AppBackgroundPicker.vue';
import EmailChangeSection from '@/components/user/EmailChangeSection.vue';
import LogoutAllDevicesSection from '@/components/user/LogoutAllDevicesSection.vue';
import PasswordChangeSection from '@/components/user/PasswordChangeSection.vue';
import { useAuthStore } from '@/stores/auth';

const props = defineProps<{
    isDeletingAccount?: boolean;
}>();

const emit = defineEmits<{
    close: [];
    deleteAccount: [];
}>();

const auth = useAuthStore();

const username = computed(() => auth.user?.username ?? '');

// Первая буква имени для круглого значка профиля
const usernameInitial = computed(() => username.value.charAt(0).toUpperCase());

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

                <section v-if="username" class="profile-section">
                    <span class="profile-avatar" aria-hidden="true">
                        {{ usernameInitial }}
                    </span>

                    <div class="profile-info">
                        <span class="profile-label">Имя пользователя</span>
                        <span class="profile-username">{{ username }}</span>
                    </div>
                </section>

                <section class="background-section">
                    <h3 class="section-title">Фон приложения</h3>

                    <AppBackgroundPicker />
                </section>

                <EmailChangeSection />

                <PasswordChangeSection />

                <LogoutAllDevicesSection />

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
@use '../../../scss/ui/accountSection.scss';

.modal {
    max-width: 360px;
    padding: 24px;
}

.profile-section {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
    padding-bottom: 18px;
    border-bottom: 1px dashed rgba(139, 92, 246, 0.2);
}

.profile-avatar {
    display: flex;
    flex-shrink: 0;
    justify-content: center;
    align-items: center;
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: linear-gradient(135deg, #a78bfa, #ec4899);
    color: #fff;
    font-size: 18px;
    font-weight: 700;
}

.profile-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
}

.profile-label {
    font-size: 12px;
    color: var(--ink-soft, #6b5878);
}

.profile-username {
    overflow: hidden;
    font-size: 15px;
    font-weight: 700;
    color: var(--ink, #241533);
    text-overflow: ellipsis;
    white-space: nowrap;
}

.background-section {
    margin-bottom: 20px;
    padding-bottom: 18px;
    border-bottom: 1px dashed rgba(139, 92, 246, 0.2);
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
