import {
    useMutation,
    type UseMutationReturnType,
    useQuery,
    useQueryClient,
} from '@tanstack/vue-query';
import type { AxiosError, AxiosResponse } from 'axios';
import { computed, type ComputedRef, ref, watch } from 'vue';
import { useRouter } from 'vue-router';

import { api } from '@/lib/api';
import { useAuthStore } from '@/stores/auth';
import type { ApiErrorResponse } from '@/types/api';
import type {
    ChangePasswordPayload,
    LoginPayload,
    RegisterPayload,
} from '@/types/auth';

export const useLogin = (): any => {
    const router = useRouter();
    const authStore = useAuthStore();
    const errorMessage = ref<string | null>(null);

    const mutation = useMutation({
        mutationFn: (payload: LoginPayload) => api.post('/v1/login', payload),

        onSuccess: (response) => {
            authStore.setUser(response.data.data);
            router.push('/dashboard');
        },

        onError: (error: any): void => {
            errorMessage.value = error?.response?.data?.data?.message;
        },
    });

    return {
        ...mutation,
        errorMessage,
    };
};

export const useRegister = (): any => {
    return useMutation({
        mutationFn: (payload: RegisterPayload) =>
            api.post('/v1/register', payload),
    });
};

export const useVerifyRegistration = () => {
    return useMutation({
        mutationFn: (payload: { email: string; code: string }) =>
            api.post('/v1/verify-registration', payload),
    });
};

export const useLogout = (): any => {
    const router = useRouter();
    const authStore = useAuthStore();

    return useMutation({
        mutationFn: () => api.post('/v1/logout'),

        onSuccess: () => {
            authStore.setUser(null);
            router.push('/');
        },
    });
};

export const useDeleteAccount = (): UseMutationReturnType<
    AxiosResponse,
    Error,
    void,
    unknown
> => {
    const router = useRouter();
    const authStore = useAuthStore();
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: () => api.delete('/v1/user'),

        onSuccess: () => {
            authStore.setUser(null);
            // Сбрасываем кэш, чтобы данные удалённого аккаунта не остались в памяти
            queryClient.clear();
            router.push('/');
        },
    });
};

type UseChangePasswordReturn = UseMutationReturnType<
    AxiosResponse,
    AxiosError<ApiErrorResponse>,
    ChangePasswordPayload,
    unknown
> & {
    errorMessage: ComputedRef<string | null>;
};

export const useChangePassword = (): UseChangePasswordReturn => {
    const mutation = useMutation<
        AxiosResponse,
        AxiosError<ApiErrorResponse>,
        ChangePasswordPayload
    >({
        mutationFn: (payload) => api.put('/v1/user/password', payload),
    });

    // Для ошибки валидации показываем первую ошибку по полю,
    // иначе — общее сообщение сервера
    const errorMessage = computed((): string | null => {
        const data = mutation.error.value?.response?.data?.data;

        if (!mutation.error.value) {
            return null;
        }

        const firstFieldError = Object.values(data?.errors ?? {})[0]?.[0];

        return firstFieldError ?? data?.message ?? 'Не удалось сменить пароль';
    });

    return {
        ...mutation,
        errorMessage,
    };
};

export const useUser = (): any => {
    const authStore = useAuthStore();

    const query = useQuery({
        queryKey: ['user'],
        queryFn: async () => {
            const { data } = await api.get('/v1/user');
            return data.data;
        },
        retry: false,
    });

    // успех
    watch(
        () => query.data.value,
        (user) => {
            if (user) {
                authStore.setUser(user);
            }
        },
    );

    // ошибка
    watch(
        () => query.error.value,
        (error) => {
            if (error) {
                authStore.setUser(null);
            }
        },
    );

    // завершение (успех или ошибка)
    watch(
        () => query.isFetched.value,
        (fetched) => {
            if (fetched) {
                authStore.initialized = true;
            }
        },
    );

    return query;
};
