<script setup lang="ts">
import { Gift, Settings } from '@lucide/vue';
import { ref } from 'vue';

import LoaderButtonSpinner from '@/components/ui/LoaderButtonSpinner.vue';
import UserSettingsModal from '@/components/user/UserSettingsModal.vue';
import { useLogout } from '@/composables/useAuth';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
const { mutate: logout, isPending } = useLogout();

const isSettingsModalOpen = ref(false);

const openSettingsModal = (): void => {
    isSettingsModalOpen.value = true;
};

const closeSettingsModal = (): void => {
    isSettingsModalOpen.value = false;
};

const handleChangePassword = (payload: {
    currentPassword: string;
    newPassword: string;
    newPasswordConfirmation: string;
}): void => {
    // TODO: подключить запрос к API после реализации бэкенда
    console.log('changePassword', payload);
};

const handleDeleteAccount = (): void => {
    // TODO: подключить запрос к API после реализации бэкенда
    console.log('Пользователь удален');
};
</script>

<template>
    <header class="header">
        <div class="container">
            <div class="logo">
                <div class="logo-icon">
                    <Gift :size="18" color="#fff" />
                    <span class="logo-spark">✦</span>
                </div>
                <span class="logo-title">PiblWish</span>
            </div>
            <div class="user-info">
                <div v-if="auth.user" class="user-details">
                    <span class="user-username">{{ auth.user.username }}</span>
                    <span class="user-email">{{ auth.user.email }}</span>
                </div>
                <div class="avatar-wrapper">
                    <div class="avatar">
                        {{ auth.user?.username?.charAt(0).toUpperCase() }}
                    </div>
                    <button
                        class="settings-btn"
                        type="button"
                        aria-label="Настройки аккаунта"
                        @click="openSettingsModal"
                    >
                        <Settings :size="11" />
                    </button>
                </div>
                <button
                    class="logout-btn"
                    :disabled="isPending"
                    @click="logout"
                >
                    <LoaderButtonSpinner v-if="isPending" :size="18" />
                    <span v-else>Выход</span>
                </button>
            </div>
        </div>

        <UserSettingsModal
            v-if="isSettingsModalOpen"
            @close="closeSettingsModal"
            @change-password="handleChangePassword"
            @delete-account="handleDeleteAccount"
        />
    </header>
</template>

<style scoped>
.header {
    position: sticky;
    top: 0;
    z-index: 10;
    background: var(--surface);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border-bottom: 1px solid var(--surface-border);
    padding: 0 28px;
    height: 68px;
    display: flex;
    justify-content: center;
    align-items: center;
}
.container {
    display: flex;
    width: 1200px;
    justify-content: space-between;
    align-items: center;
}

.logo {
    display: flex;
    align-items: center;
    gap: 12px;
}
.logo-icon {
    width: 38px;
    height: 38px;
    border-radius: 14px;
    background: var(--logo-gradient);
    display: grid;
    place-items: center;
    font-size: 18px;
    position: relative;
    box-shadow: var(--shadow-glow);
    transition: transform 0.25s ease;
}
.logo:hover .logo-icon {
    transform: rotate(-6deg) scale(1.05);
}
.logo-spark {
    position: absolute;
    top: 2px;
    right: 3px;
    font-size: 9px;
    color: var(--brand-amber);
    animation: sparkle 2.4s ease-in-out infinite;
}
@keyframes sparkle {
    0%,
    100% {
        opacity: 0.5;
        transform: scale(0.85);
    }
    50% {
        opacity: 1;
        transform: scale(1.15);
    }
}
.logo-title {
    font-size: 20px;
    font-weight: 800;
    background: var(--logo-gradient);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
    letter-spacing: -0.04em;
}
.user-info {
    display: flex;
    align-items: center;
    gap: 14px;
    font-size: 14px;
    color: var(--ink);
}

.user-details {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
}

.user-username {
    font-weight: 700;
}
.user-email {
    font-size: 12px;
    color: var(--ink-soft);
}

.avatar-wrapper {
    position: relative;
}

.avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: var(--brand-gradient);
    display: grid;
    place-items: center;
    font-size: 13px;
    font-weight: 700;
    color: #fff;
    box-shadow: var(--shadow-glow);
    border: 2px solid #fff;
}

.settings-btn {
    position: absolute;
    bottom: -2px;
    right: -2px;
    display: flex;
    justify-content: center;
    align-items: center;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    border: 2px solid #fff;
    background: var(--brand-amber);
    color: #4a2e00;
    cursor: pointer;
    transition: all 0.2s ease;
}
.settings-btn:hover {
    transform: scale(1.15) rotate(45deg);
}

.logout-btn {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 6px;
    background: rgba(139, 92, 246, 0.08);
    border: 1px solid transparent;
    padding: 6px 16px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    color: var(--brand-violet);
    cursor: pointer;
    font-family: inherit;
    transition: all 0.2s ease;
    height: 36px;
}
.logout-btn:hover:not(:disabled) {
    background: var(--brand-gradient);
    color: #fff;
    box-shadow: var(--shadow-glow);
    transform: translateY(-1px);
}
</style>
