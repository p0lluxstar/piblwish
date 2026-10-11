import type { RouteRecordRaw } from 'vue-router';
import { createRouter, createWebHistory } from 'vue-router';

import DashboardLayout from '@/layouts/DashboardLayout.vue';
import LandingLayout from '@/layouts/LandingLayout.vue';
import ContactsPage from '@/pages/ContactsPage.vue';
import DashboardPage from '@/pages/DashboardPage.vue';
import ForgotPasswordPage from '@/pages/ForgotPasswordPage.vue';
import HelpPage from '@/pages/HelpPage.vue';
import LoginPage from '@/pages/LoginPage.vue';
import MainPage from '@/pages/MainPage.vue';
import RegistrationPage from '@/pages/RegistrationPage.vue';
import SavedWishlistsPage from '@/pages/SavedWishlistsPage.vue';
import WishlistSharedPage from '@/pages/WishlistSharedPage.vue';
import { useAuthStore } from '@/stores/auth';

const routes: RouteRecordRaw[] = [
    {
        path: '/',
        component: LandingLayout,
        meta: { requiresGuest: true },
        children: [
            {
                path: '',
                name: 'main',
                component: MainPage,
            },
            {
                path: 'registration',
                name: 'registration',
                component: RegistrationPage,
            },
            {
                path: 'login',
                name: 'login',
                component: LoginPage,
            },
            {
                path: 'forgot-password',
                name: 'forgot-password',
                component: ForgotPasswordPage,
            },
        ],
    },

    {
        path: '/dashboard',
        component: DashboardLayout,
        meta: { requiresAuth: true },
        children: [
            {
                path: '',
                name: 'dashboard',
                component: DashboardPage,
            },
            {
                // Архив: та же страница, но со списками из архива, только для просмотра
                path: 'archive',
                name: 'dashboard-archive',
                component: DashboardPage,
                props: { archived: true },
            },
        ],
    },

    {
        // Чужие списки, добавленные к себе с общей страницы
        path: '/saved',
        component: DashboardLayout,
        meta: { requiresAuth: true },
        children: [
            {
                path: '',
                name: 'saved-wishlists',
                component: SavedWishlistsPage,
            },
        ],
    },

    {
        // Публичная страница: доступна всем, но шапка зависит от того, авторизован ли пользователь
        // У списка дел и сбора свои адреса; страница после загрузки исправляет адрес,
        // если он не соответствует типу списка (lib/sharedLink.ts)
        path: '/shared-wishlist/:id',
        alias: ['/shared-todolist/:id', '/shared-fund/:id'],
        component: DashboardLayout,
        meta: { loadUser: true },
        children: [
            {
                path: '',
                name: 'shared-wishlist',
                component: WishlistSharedPage,
            },
        ],
    },

    // Прежние адреса общей страницы: по ним открываются уже разосланные ссылки.
    // Страница после загрузки исправляет адрес по типу списка
    ...['/shared/:id', '/shared-wishlists/:id', '/shared-todolists/:id'].map(
        (path): RouteRecordRaw => ({
            path,
            redirect: (to) => ({
                name: 'shared-wishlist',
                params: to.params,
                query: to.query,
                hash: to.hash,
            }),
        }),
    ),

    {
        // Публичные страницы с фоном и шапкой дашборда
        path: '/help',
        component: DashboardLayout,
        meta: { loadUser: true },
        children: [
            {
                path: '',
                name: 'help',
                component: HelpPage,
            },
        ],
    },

    {
        path: '/contacts',
        component: DashboardLayout,
        meta: { loadUser: true },
        children: [
            {
                path: '',
                name: 'contacts',
                component: ContactsPage,
            },
        ],
    },
];

// Инициализация Vue Router для управления навигацией приложения
const router = createRouter({
    history: createWebHistory(),
    routes,
});

/**
 * Глобальный навигационный страж (Global Before Guard)
 * Выполняется перед каждым переходом между страницами
 *
 * @param to - Объект целевого маршрута (куда пользователь хочет перейти)
 * @param from - Объект текущего маршрута (откуда пользователь уходит)
 * @returns true - разрешить переход, false - отменить переход, '/path' - перенаправить на другой маршрут
 */

router.beforeEach(async (to) => {
    const auth = useAuthStore();

    // Проверка, требуется ли аутентификация, гостевой доступ или данные пользователя для целевого маршрута
    if (to.meta.requiresAuth || to.meta.requiresGuest || to.meta.loadUser) {
        if (!auth.initialized) {
            await auth.fetchUser();
        }
    }

    const isAuth = !!auth.user;

    if (to.meta.requiresAuth && !isAuth) {
        return '/';
    }

    if (to.meta.requiresGuest && isAuth) {
        return '/dashboard';
    }

    return true;
});

export default router;
