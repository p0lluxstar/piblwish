<script setup lang="ts">
import {
    Bookmark,
    CircleQuestionMark,
    Gift,
    LayoutList,
    LogOut,
    Settings,
} from '@lucide/vue';
import { computed, ref } from 'vue';

import LoaderButtonSpinner from '@/components/ui/LoaderButtonSpinner.vue';
import UserAvatar from '@/components/ui/UserAvatar.vue';
import UserSettingsModal from '@/components/user/UserSettingsModal.vue';
import { useDeleteAccount, useLogout } from '@/composables/useAuth';
import { useMotivationalPhrase } from '@/composables/useMotivationalPhrase';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
const { phrase, longestPhrase } = useMotivationalPhrase();

const logoLink = computed(() => (auth.user ? '/dashboard' : '/'));
const { mutate: logout, isPending } = useLogout();
const { mutate: deleteAccount, isPending: isDeletingAccount } =
    useDeleteAccount();

const isSettingsModalOpen = ref(false);

const openSettingsModal = (): void => {
    isSettingsModalOpen.value = true;
};

const closeSettingsModal = (): void => {
    isSettingsModalOpen.value = false;
};

const handleDeleteAccount = (): void => {
    deleteAccount();
};
</script>

<template>
    <header class="header">
        <div class="header-inner">
            <!-- Авторизованный пользователь попадает на свои карточки, гость — на главную -->
            <router-link :to="logoLink" class="logo" aria-label="PiblWish">
                <div class="logo-icon">
                    <Gift :size="18" color="#fff" />
                    <span class="logo-spark">✦</span>
                </div>
                <span class="logo-text">
                    <!-- На узком экране вместо полного названия — сокращение -->
                    <span class="logo-title" aria-hidden="true">
                        <span class="logo-title-full">PiblWish</span>
                        <span class="logo-title-short">PW</span>
                    </span>
                    <!-- Фраза обращена к владельцу списков, поэтому гостю не показывается.
                         Невидимая самая длинная фраза лежит в той же ячейке и задаёт
                         ширину блока, поэтому при смене фразы он не меняет размер -->
                    <span
                        v-if="auth.user"
                        class="logo-phrase-box"
                        aria-hidden="true"
                    >
                        <span class="logo-phrase logo-phrase-sizer">
                            {{ longestPhrase }}
                        </span>
                        <!-- Ключ пересоздаёт элемент при смене фразы, чтобы анимация срабатывала заново -->
                        <span :key="phrase" class="logo-phrase">
                            {{ phrase }}
                        </span>
                    </span>
                </span>
            </router-link>
            <nav class="header-nav">
                <!-- Свои карточки есть только у авторизованного пользователя -->
                <router-link
                    v-if="auth.user"
                    to="/dashboard"
                    class="nav-link"
                    aria-label="Мои карточки"
                >
                    <LayoutList :size="16" />
                    <span class="btn-text">Мои карточки</span>
                </router-link>
                <!-- Чужие списки, добавленные к себе с общей страницы -->
                <router-link
                    v-if="auth.user"
                    to="/saved"
                    class="nav-link"
                    aria-label="Чужие списки"
                >
                    <Bookmark :size="16" />
                    <span class="btn-text">Чужие списки</span>
                </router-link>
                <router-link to="/help" class="nav-link" aria-label="Помощь">
                    <CircleQuestionMark :size="16" />
                    <span class="btn-text">Помощь</span>
                </router-link>
            </nav>
            <div v-if="auth.user" class="user-info">
                <div class="user-details">
                    <span class="user-username">{{ auth.user.username }}</span>
                    <span class="user-email">{{ auth.user.email }}</span>
                </div>
                <div class="avatar-wrapper">
                    <UserAvatar
                        class="avatar"
                        :username="auth.user.username"
                        :avatar-url="auth.user.avatarUrl"
                    />
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
            :is-deleting-account="isDeletingAccount"
            @close="closeSettingsModal"
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
/* Три колонки: логотип, меню и пользователь. Боковые колонки равной ширины,
   поэтому меню стоит по центру шапки и не зависит от ширины соседей.
   Класс не называется container: у одноимённого класса Tailwind max-width по
   брейкпоинтам, и на ширине меньше 1280px содержимое шапки сжималось к центру */
.header-inner {
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    width: 1200px;
    align-items: center;
    gap: 16px;
}

.logo {
    display: flex;
    justify-self: start;
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
    flex-shrink: 0;
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
/* Название и фраза под ним. В сетке у блока фразы с overflow: hidden нулевой минимальный
   размер, поэтому при нехватке места блок сжимается до ширины названия, а фраза обрезается */
.logo-text {
    display: grid;
    line-height: 1.2;
}
.logo-phrase-box {
    display: grid;
    overflow: hidden;
}
/* Текущая фраза и невидимая самая длинная лежат в одной ячейке */
.logo-phrase-box > * {
    grid-area: 1 / 1;
}
.logo-phrase-sizer {
    visibility: hidden;
}
.logo-phrase {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 11px;
    font-weight: 600;
    color: var(--brand-pink);
    animation: phrase-in 0.4s ease-out;
}
@media (prefers-reduced-motion: reduce) {
    .logo-phrase {
        animation: none;
    }
}
@keyframes phrase-in {
    from {
        opacity: 0;
        transform: translateY(4px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
/* Меню не сжимается: при нехватке места обрезаются имя и email,
   а у логотипа фраза, но не название */
.header-nav {
    display: flex;
    justify-self: center;
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
    /* Без этого на ширине 768–1023px «Мои карточки» переносилось на две строки */
    white-space: nowrap;
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
/* Блок занимает всю колонку и прижимает содержимое вправо: при justify-self: end
   он не сжимался бы и длинный email заезжал бы на меню */
.user-info {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 14px;
    min-width: 0;
    font-size: 14px;
    color: var(--ink);
}

/* Длинные имя и email обрезаются многоточием, а не сдвигают меню */
.user-details {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    min-width: 0;
}

.user-username,
.user-email {
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
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
    width: 44px;
    height: 44px;
    background: var(--brand-gradient);
    font-size: 16px;
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
    width: 20px;
    height: 20px;
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
    justify-content: flex-end;
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

/* Планшет и уже: у ссылок навигации и кнопки «Выход» остаются только значки */
@media (max-width: 767px) {
    .header-nav {
        gap: 0;
    }
    .nav-link,
    .logout-btn {
        width: 36px;
        padding: 0;
        justify-content: center;
    }
    .btn-text {
        display: none;
    }
}

/* Узкий экран: одна строка, имя и email скрыты */
@media (max-width: 599px) {
    .header {
        height: 60px;
        padding: 0 16px;
    }
    .header-inner {
        width: 100%;
        gap: 8px;
    }
    .user-info {
        gap: 10px;
    }
    .user-details {
        display: none;
    }
    .avatar {
        width: 40px;
        height: 40px;
        font-size: 15px;
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
    /* Под сокращением PW фразе не хватает места */
    .logo-phrase-box {
        display: none;
    }

    .guest-actions {
        gap: 8px;
    }
    .guest-btn {
        padding: 6px 14px;
    }
}
</style>
