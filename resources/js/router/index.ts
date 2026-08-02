import type { RouteRecordRaw } from 'vue-router';
import { createRouter, createWebHistory } from 'vue-router';

import DashboardLayout from '@/layouts/DashboardLayout.vue';
import LandingLayout from '@/layouts/LandingLayout.vue';
import DashboardPage from '@/pages/DashboardPage.vue';
import LoginPage from '@/pages/LoginPage.vue';
import MainPage from '@/pages/MainPage.vue';
import RegistrationPage from '@/pages/RegistrationPage.vue';
import WishlistViewPage from '@/pages/WishlistViewPage.vue';
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
        ],
    },

    {
        path: '/wishlists/:id',
        name: 'wishlist',
        component: WishlistViewPage,
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

    // Проверка, требуется ли аутентификация или гостевой доступ для целевого маршрута
    if (to.meta.requiresAuth || to.meta.requiresGuest) {
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
