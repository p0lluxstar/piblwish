import { defineStore } from 'pinia';

import { api } from '@/lib/api';
import { User } from '@/types/user';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null as User | null,
        loading: false,
        initialized: false,
        isVerified: false,
    }),

    getters: {
        isAuthenticated: (state) => !!state.user,
    },

    actions: {
        setUser(user: User | null) {
            this.user = user;
        },

        // Получение данных текущего пользователя из API и обновление состояния аутентификации
        async fetchUser() {
            if (this.loading) return;
            if (this.initialized) return;

            this.loading = true;

            try {
                const { data } = await api.get('/v1/user');
                this.user = data.data;
            } catch (error: any) {
                if (error?.response?.status === 401) {
                    this.user = null;
                } else {
                    this.user = null;
                }
            } finally {
                this.initialized = true;
                this.loading = false;
            }
        },

        // async login(payload: { email: string; password: string }) {
        //     await axios.get('/sanctum/csrf-cookie');
        //     await axios.post('/v1/login', payload);
        //     await this.fetchUser();
        // },

        // async logout() {
        //     await axios.post('/v1/logout');
        //     this.user = null;
        //     this.initialized = true;
        // },
    },
});
