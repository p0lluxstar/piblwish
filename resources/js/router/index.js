import { createRouter, createWebHistory } from 'vue-router';
import LoginForm from '../../components/LoginForm.vue';

const routes = [
    {
        path: '/',
        component: LoginForm,
    },
    {
        path: '/login',
        component: LoginForm,
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

export default router;
