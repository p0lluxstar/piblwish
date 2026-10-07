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
    ConfirmEmailChangePayload,
    ForgotPasswordPayload,
    LoginPayload,
    RegisterPayload,
    RequestEmailChangePayload,
    ResetPasswordPayload,
} from '@/types/auth';
import type { AppBackground, User } from '@/types/user';

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

    const errorMessage = computed((): string | null =>
        getApiErrorMessage(mutation.error.value, 'Не удалось сменить пароль'),
    );

    return {
        ...mutation,
        errorMessage,
    };
};

type UseRequestEmailChangeReturn = UseMutationReturnType<
    AxiosResponse,
    AxiosError<ApiErrorResponse>,
    RequestEmailChangePayload,
    unknown
> & {
    errorMessage: ComputedRef<string | null>;
};

// Первый шаг смены email: код уходит на новый адрес
export const useRequestEmailChange = (): UseRequestEmailChangeReturn => {
    const mutation = useMutation<
        AxiosResponse,
        AxiosError<ApiErrorResponse>,
        RequestEmailChangePayload
    >({
        mutationFn: (payload) => api.post('/v1/user/email', payload),
    });

    const errorMessage = computed((): string | null =>
        getApiErrorMessage(mutation.error.value, 'Не удалось отправить код'),
    );

    return {
        ...mutation,
        errorMessage,
    };
};

type UseConfirmEmailChangeReturn = UseMutationReturnType<
    AxiosResponse<{ data: User }>,
    AxiosError<ApiErrorResponse>,
    ConfirmEmailChangePayload,
    unknown
> & {
    errorMessage: ComputedRef<string | null>;
};

// Второй шаг смены email: сервер возвращает пользователя с новым адресом
export const useConfirmEmailChange = (): UseConfirmEmailChangeReturn => {
    const authStore = useAuthStore();
    const queryClient = useQueryClient();

    const mutation = useMutation<
        AxiosResponse<{ data: User }>,
        AxiosError<ApiErrorResponse>,
        ConfirmEmailChangePayload
    >({
        mutationFn: (payload) => api.post('/v1/user/email/confirm', payload),

        onSuccess: (response) => {
            const user = response.data.data;

            authStore.setUser(user);
            queryClient.setQueryData(['user'], user);
        },
    });

    const errorMessage = computed((): string | null =>
        getApiErrorMessage(mutation.error.value, 'Не удалось сменить email'),
    );

    return {
        ...mutation,
        errorMessage,
    };
};

type UseUpdateBackgroundReturn = UseMutationReturnType<
    AxiosResponse,
    AxiosError<ApiErrorResponse>,
    AppBackground,
    { previous: AppBackground | undefined }
> & {
    errorMessage: ComputedRef<string | null>;
};

// Смена фона приложения. Фон меняется сразу, до ответа сервера;
// при ошибке возвращается прежний
export const useUpdateBackground = (): UseUpdateBackgroundReturn => {
    const authStore = useAuthStore();

    const setBackground = (background: AppBackground): void => {
        if (authStore.user) {
            authStore.setUser({ ...authStore.user, background });
        }
    };

    const mutation = useMutation<
        AxiosResponse,
        AxiosError<ApiErrorResponse>,
        AppBackground,
        { previous: AppBackground | undefined }
    >({
        mutationFn: (background) => api.patch('/v1/user', { background }),

        onMutate: (background) => {
            const previous = authStore.user?.background;
            setBackground(background);

            return { previous };
        },

        onError: (_error, background, context) => {
            // Если пользователь уже выбрал другой фон, ошибка прежнего запроса его не сбрасывает
            if (
                context?.previous &&
                authStore.user?.background === background
            ) {
                setBackground(context.previous);
            }
        },
    });

    const errorMessage = computed((): string | null =>
        getApiErrorMessage(mutation.error.value, 'Не удалось сменить фон'),
    );

    return {
        ...mutation,
        errorMessage,
    };
};

type UseForgotPasswordReturn = UseMutationReturnType<
    AxiosResponse,
    AxiosError<ApiErrorResponse>,
    ForgotPasswordPayload,
    unknown
> & {
    errorMessage: ComputedRef<string | null>;
};

export const useForgotPassword = (): UseForgotPasswordReturn => {
    const mutation = useMutation<
        AxiosResponse,
        AxiosError<ApiErrorResponse>,
        ForgotPasswordPayload
    >({
        mutationFn: (payload) => api.post('/v1/forgot-password', payload),
    });

    const errorMessage = computed((): string | null =>
        getApiErrorMessage(mutation.error.value, 'Не удалось отправить код'),
    );

    return {
        ...mutation,
        errorMessage,
    };
};

type UseResetPasswordReturn = UseMutationReturnType<
    AxiosResponse,
    AxiosError<ApiErrorResponse>,
    ResetPasswordPayload,
    unknown
> & {
    errorMessage: ComputedRef<string | null>;
};

export const useResetPassword = (): UseResetPasswordReturn => {
    const mutation = useMutation<
        AxiosResponse,
        AxiosError<ApiErrorResponse>,
        ResetPasswordPayload
    >({
        mutationFn: (payload) => api.post('/v1/reset-password', payload),
    });

    const errorMessage = computed((): string | null =>
        getApiErrorMessage(mutation.error.value, 'Не удалось сменить пароль'),
    );

    return {
        ...mutation,
        errorMessage,
    };
};

// Для ошибки валидации возвращает первую ошибку по полю,
// иначе — общее сообщение сервера
function getApiErrorMessage(
    error: AxiosError<ApiErrorResponse> | null,
    fallback: string,
): string | null {
    if (!error) {
        return null;
    }

    // Для 429 обработчик исключений отдаёт английский текст фреймворка
    if (error.response?.status === 429) {
        return 'Слишком много попыток. Подождите минуту и попробуйте снова';
    }

    const data = error.response?.data?.data;
    const firstFieldError = Object.values(data?.errors ?? {})[0]?.[0];

    return firstFieldError ?? data?.message ?? fallback;
}

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
