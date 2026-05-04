import type { RouteRecordRaw } from 'vue-router';
import { createRouter, createWebHistory } from 'vue-router';

import DashboardLayout from '@/layouts/DashboardLayout.vue';
import LandingLayout from '@/layouts/LandingLayout.vue';
import DashboardPage from '@/pages/DashboardPage.vue';
import LoginPage from '@/pages/LoginPage.vue';
import MainPage from '@/pages/MainPage.vue';
import RegistrationPage from '@/pages/RegistrationPage.vue';

const routes: RouteRecordRaw[] = [
    {
        path: '/',
        component: LandingLayout,
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
        children: [
            {
                path: '',
                name: 'dashboard',
                component: DashboardPage,
            },
        ],
    },
];

// Инициализация Vue Router для управления навигацией приложения
const router = createRouter({
    history: createWebHistory(),
    routes,
});

export default router;
