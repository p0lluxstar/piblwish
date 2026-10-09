import type { QueryClient } from '@tanstack/vue-query';
import {
    type AxiosResponse,
    type InternalAxiosRequestConfig,
    isAxiosError,
} from 'axios';
import type { Router } from 'vue-router';

import { api } from '@/lib/api';
import { useAuthStore } from '@/stores/auth';

type RetriableRequestConfig = InternalAxiosRequestConfig & {
    // Запрос уже повторён после обновления CSRF-токена
    csrfRetried?: boolean;
};

// Один запрос нового CSRF-токена на все запросы, получившие 419 одновременно
let csrfRefresh: Promise<unknown> | null = null;

const refreshCsrfToken = (): Promise<unknown> => {
    csrfRefresh ??= api.get('/sanctum/csrf-cookie').finally(() => {
        csrfRefresh = null;
    });

    return csrfRefresh;
};

// Обработка истёкшей сессии.
//
// 419: после долгого перерыва сессии и cookie XSRF-TOKEN уже нет, и сервер
// отклоняет POST/PUT/PATCH/DELETE по CSRF раньше, чем проверяет вход.
// Получаем новую сессию с CSRF-токеном и один раз повторяем запрос: при
// «Запомнить меня» вход восстановится по cookie, иначе повтор получит 401.
//
// 401 на запрос вошедшего пользователя: сессия истекла, а cookie «Запомнить
// меня» нет или она недействительна. Пользователь выходит из аккаунта на
// клиенте и попадает на страницу входа. 401 гостя (неверный пароль при входе,
// проверка сессии при загрузке) не трогаем
export function setupAuthInterceptor(
    router: Router,
    queryClient: QueryClient,
): void {
    api.interceptors.response.use(
        undefined,
        async (error: unknown): Promise<AxiosResponse> => {
            if (!isAxiosError(error)) {
                throw error;
            }

            const config = error.config as RetriableRequestConfig | undefined;

            if (
                error.response?.status === 419 &&
                config &&
                !config.csrfRetried
            ) {
                config.csrfRetried = true;

                try {
                    await refreshCsrfToken();
                } catch {
                    throw error;
                }

                // Axios заново подставит заголовок X-XSRF-TOKEN из новой cookie
                return api.request(config);
            }

            if (error.response?.status === 401) {
                const auth = useAuthStore();

                // Несколько одновременных 401 обрабатываются один раз:
                // после первого пользователь уже сброшен
                if (auth.user) {
                    auth.setUser(null);
                    // Убираем из кэша данные аккаунта
                    queryClient.clear();
                    router.push({ name: 'login', query: { expired: '1' } });
                }
            }

            throw error;
        },
    );
}
