<script setup lang="ts">
import {
    CircleQuestionMark,
    Gift,
    LayoutList,
    LogOut,
    Mail,
    Settings,
} from '@lucide/vue';
import { computed, ref } from 'vue';

import LoaderButtonSpinner from '@/components/ui/LoaderButtonSpinner.vue';
import UserSettingsModal from '@/components/user/UserSettingsModal.vue';
import {
    useChangePassword,
    useDeleteAccount,
    useLogout,
} from '@/composables/useAuth';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();

const logoLink = computed(() => (auth.user ? '/dashboard' : '/'));
const { mutate: logout, isPending } = useLogout();
const { mutate: deleteAccount, isPending: isDeletingAccount } =
    useDeleteAccount();
const {
    mutate: changePassword,
    isPending: isChangingPassword,
    isSuccess: isPasswordChanged,
    errorMessage: passwordErrorMessage,
    reset: resetChangePassword,
} = useChangePassword();

const isSettingsModalOpen = ref(false);

const openSettingsModal = (): void => {
    isSettingsModalOpen.value = true;
};

const closeSettingsModal = (): void => {
    isSettingsModalOpen.value = false;
    // При повторном открытии модалки не показываем результат прошлой попытки
    resetChangePassword();
};

const handleChangePassword = (payload: {
    currentPassword: string;
    newPassword: string;
    newPasswordConfirmation: string;
}): void => {
    changePassword({
        current_password: payload.currentPassword,
        password: payload.newPassword,
        password_confirmation: payload.newPasswordConfirmation,
    });
};

const handleDeleteAccount = (): void => {
    deleteAccount();
};
</script>

<template>
    <header class="header">
        <div class="container">
            <!-- Авторизованный пользователь попадает на свои списки, гость — на главную -->
            <div class="header-left">
                <router-link :to="logoLink" class="logo" aria-label="PiblWish">
                    <div class="logo-icon">
                        <Gift :size="18" color="#fff" />
                        <span class="logo-spark">✦</span>
                    </div>
                    <!-- На узком экране вместо полного названия — сокращение -->
                    <span class="logo-title" aria-hidden="true">
                        <span class="logo-title-full">PiblWish</span>
                        <span class="logo-title-short">PW</span>
                    </span>
                </router-link>
                <nav class="header-nav">
                    <!-- Свои списки есть только у авторизованного пользователя -->
                    <router-link
                        v-if="auth.user"
                        to="/dashboard"
                        class="nav-link"
                        aria-label="Мои списки"
                    >
                        <LayoutList :size="16" />
                        <span class="btn-text">Мои списки</span>
                    </router-link>
                    <router-link
                        to="/help"
                        class="nav-link"
                        aria-label="Помощь"
                    >
                        <CircleQuestionMark :size="16" />
                        <span class="btn-text">Помощь</span>
                    </router-link>
                    <router-link
                        to="/contacts"
                        class="nav-link"
                        aria-label="Контакты"
                    >
                        <Mail :size="16" />
                        <span class="btn-text">Контакты</span>
                    </router-link>
                </nav>
            </div>
            <div v-if="auth.user" class="user-info">
                <div class="user-details">
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
                    aria-label="Выход"
                    :disabled="isPending"
                    @click="logout"
                >
                    <!-- Содержимое остаётся в разметке и задаёт ширину кнопки,
                         во время выхода оно скрыто, а спиннер выводится поверх -->
                    <span
                        class="logout-content"
                        :class="{ 'is-hidden': isPending }"
                    >
                        <LogOut :size="16" />
                        <span class="btn-text">Выход</span>
                    </span>
                    <LoaderButtonSpinner
                        v-if="isPending"
                        class="logout-spinner"
                        :size="18"
                    />
                </button>
            </div>
            <div v-else class="guest-actions">
                <router-link to="/registration" class="guest-btn">
                    Регистрация
                </router-link>
                <router-link to="/login" class="guest-btn guest-btn--primary">
                    Войти
                </router-link>
            </div>
        </div>

        <UserSettingsModal
            v-if="auth.user && isSettingsModalOpen"
            :is-pending="isChangingPassword"
            :is-deleting-account="isDeletingAccount"
            :password-error-message="passwordErrorMessage"
            :is-password-changed="isPasswordChanged"
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
    text-decoration: none;
}
.logo-icon {
    width: 38px;
    height: 38px;
    border-radius: 10px;
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
.logo-title-short {
    display: none;
}
.header-left {
    display: flex;
    align-items: center;
    gap: 28px;
}
.header-nav {
    display: flex;
    align-items: center;
    gap: 4px;
}
.nav-link {
    display: flex;
    align-items: center;
    gap: 6px;
    height: 36px;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    color: var(--ink-soft);
    text-decoration: none;
    transition: all 0.2s ease;
}
.nav-link:hover {
    color: var(--brand-violet);
    background: rgba(139, 92, 246, 0.08);
}
/* Ссылка на открытую страницу: заливка фирменным градиентом, чтобы отличаться от наведения */
.nav-link.router-link-active {
    color: #fff;
    background: var(--brand-gradient);
    box-shadow: var(--shadow-glow);
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
.logout-btn {
    position: relative;
}
.logout-content {
    display: flex;
    align-items: center;
    gap: 6px;
}
.logout-content.is-hidden {
    visibility: hidden;
}
/* Центрирование через inset и margin: transform занят анимацией вращения спиннера */
.logout-spinner {
    position: absolute;
    inset: 0;
    margin: auto;
}
.logout-btn:hover:not(:disabled) {
    background: var(--brand-gradient);
    color: #fff;
    box-shadow: var(--shadow-glow);
    transform: translateY(-1px);
}

.guest-actions {
    display: flex;
    align-items: center;
    gap: 10px;
}

.guest-btn {
    display: flex;
    justify-content: center;
    align-items: center;
    height: 36px;
    padding: 6px 18px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    color: var(--brand-violet);
    background: rgba(139, 92, 246, 0.08);
    text-decoration: none;
    transition: all 0.2s ease;
}
.guest-btn:hover {
    background: rgba(139, 92, 246, 0.14);
    transform: translateY(-1px);
}

.guest-btn--primary {
    color: #fff;
    background: var(--brand-gradient);
    box-shadow: var(--shadow-glow);
}
.guest-btn--primary:hover {
    background: var(--brand-gradient);
    filter: brightness(1.05);
}

/* Планшет и уже: у ссылок навигации остаются только значки */
@media (max-width: 767px) {
    .header-nav {
        gap: 0;
    }
    .nav-link {
        width: 36px;
        padding: 0;
        justify-content: center;
    }
    .nav-link .btn-text {
        display: none;
    }
}

/* Узкий экран: одна строка, имя и email скрыты, кнопки только с иконками */
@media (max-width: 599px) {
    .header {
        height: 60px;
        padding: 0 16px;
    }
    .container {
        width: 100%;
    }
    .user-info {
        gap: 10px;
    }
    .user-details,
    .btn-text {
        display: none;
    }
    .logout-btn {
        width: 36px;
        padding: 0;
        justify-content: center;
    }
    .header-left {
        gap: 8px;
    }
    /* На узком экране вместо полного названия — сокращение PW */
    .logo {
        gap: 8px;
    }
    .logo-title-full {
        display: none;
    }
    .logo-title-short {
        display: inline;
    }

    .guest-actions {
        gap: 8px;
    }
    .guest-btn {
        padding: 6px 14px;
    }
}
</style>
