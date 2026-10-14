import {
    useMutation,
    type UseMutationReturnType,
    useQuery,
    useQueryClient,
    type UseQueryReturnType,
} from '@tanstack/vue-query';
import type { AxiosError, AxiosResponse } from 'axios';
import { computed, type ComputedRef, watch } from 'vue';
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

type UseLoginReturn = UseMutationReturnType<
    AxiosResponse<{ data: User }>,
    AxiosError<ApiErrorResponse>,
    LoginPayload,
    unknown
> & {
    errorMessage: ComputedRef<string | null>;
};

export const useLogin = (): UseLoginReturn => {
    const router = useRouter();
    const authStore = useAuthStore();

    const mutation = useMutation<
        AxiosResponse<{ data: User }>,
        AxiosError<ApiErrorResponse>,
        LoginPayload
    >({
        mutationFn: (payload) => api.post('/v1/login', payload),

        onSuccess: (response) => {
            authStore.setUser(response.data.data);
            router.push('/dashboard');
        },
    });

    const errorMessage = computed((): string | null =>
        getApiErrorMessage(
            mutation.error.value,
            'Не удалось войти. Попробуйте позже',
        ),
    );

    return {
        ...mutation,
        errorMessage,
    };
};

type UseRegisterReturn = UseMutationReturnType<
    AxiosResponse,
    AxiosError<ApiErrorResponse>,
    RegisterPayload,
    unknown
> & {
    errorMessage: ComputedRef<string | null>;
};

export const useRegister = (): UseRegisterReturn => {
    const mutation = useMutation<
        AxiosResponse,
        AxiosError<ApiErrorResponse>,
        RegisterPayload
    >({
        mutationFn: (payload) => api.post('/v1/register', payload),
    });

    const errorMessage = computed((): string | null =>
        getApiErrorMessage(
            mutation.error.value,
            'Не удалось зарегистрироваться. Попробуйте позже',
        ),
    );

    return {
        ...mutation,
        errorMessage,
    };
};

type VerifyRegistrationPayload = { email: string; code: string };

type UseVerifyRegistrationReturn = UseMutationReturnType<
    AxiosResponse,
    AxiosError<ApiErrorResponse>,
    VerifyRegistrationPayload,
    unknown
> & {
    errorMessage: ComputedRef<string | null>;
};

export const useVerifyRegistration = (): UseVerifyRegistrationReturn => {
    const mutation = useMutation<
        AxiosResponse,
        AxiosError<ApiErrorResponse>,
        VerifyRegistrationPayload
    >({
        mutationFn: (payload) => api.post('/v1/verify-registration', payload),
    });

    const errorMessage = computed((): string | null =>
        getApiErrorMessage(
            mutation.error.value,
            'Не удалось подтвердить код. Попробуйте позже',
        ),
    );

    return {
        ...mutation,
        errorMessage,
    };
};

type ResendVerificationCodePayload = { email: string };

type UseResendVerificationCodeReturn = UseMutationReturnType<
    AxiosResponse,
    AxiosError<ApiErrorResponse>,
    ResendVerificationCodePayload,
    unknown
> & {
    errorMessage: ComputedRef<string | null>;
};

export const useResendVerificationCode =
    (): UseResendVerificationCodeReturn => {
        const mutation = useMutation<
            AxiosResponse,
            AxiosError<ApiErrorResponse>,
            ResendVerificationCodePayload
        >({
            mutationFn: (payload) =>
                api.post('/v1/verify-registration/resend', payload),
        });

        const errorMessage = computed((): string | null =>
            getApiErrorMessage(
                mutation.error.value,
                'Не удалось отправить код. Попробуйте позже',
            ),
        );

        return {
            ...mutation,
            errorMessage,
        };
    };

export const useLogout = (): UseMutationReturnType<
    AxiosResponse,
    Error,
    void,
    unknown
> => {
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

// Выход на всех устройствах, включая текущее
export const useLogoutAllDevices = (): UseMutationReturnType<
    AxiosResponse,
    AxiosError<ApiErrorResponse>,
    void,
    unknown
> => {
    const router = useRouter();
    const authStore = useAuthStore();
    const queryClient = useQueryClient();

    return useMutation<AxiosResponse, AxiosError<ApiErrorResponse>, void>({
        mutationFn: () => api.post('/v1/logout-all'),

        onSuccess: () => {
            authStore.setUser(null);
            // Сбрасываем кэш, чтобы данные аккаунта не остались в памяти
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

// Логическая настройка пользователя, которая меняется переключателем в окне настроек
export type UserToggleSetting = 'showFriendsEvents' | 'emailReminders';

type UseUpdateUserToggleReturn = UseMutationReturnType<
    AxiosResponse,
    AxiosError<ApiErrorResponse>,
    boolean,
    unknown
> & {
    errorMessage: ComputedRef<string | null>;
};

// Изменение логической настройки: строки «Скоро у друзей» в дашборде или писем
// о сроке списков. Переключатель меняется сразу, до ответа сервера;
// при ошибке возвращается прежнее значение
export const useUpdateUserToggle = (
    setting: UserToggleSetting,
): UseUpdateUserToggleReturn => {
    const authStore = useAuthStore();

    const setValue = (value: boolean): void => {
        if (authStore.user) {
            authStore.setUser({ ...authStore.user, [setting]: value });
        }
    };

    const mutation = useMutation<
        AxiosResponse,
        AxiosError<ApiErrorResponse>,
        boolean
    >({
        mutationFn: (value) => api.patch('/v1/user', { [setting]: value }),

        onMutate: (value) => {
            setValue(value);
        },

        // Значение после ошибки противоположно отправленному. Если пользователь
        // уже переключил ещё раз, состояние и так совпадает с этим значением
        onError: (_error, value) => {
            if (authStore.user?.[setting] === value) {
                setValue(!value);
            }
        },
    });

    const errorMessage = computed((): string | null =>
        getApiErrorMessage(
            mutation.error.value,
            'Не удалось сохранить настройку',
        ),
    );

    return {
        ...mutation,
        errorMessage,
    };
};

type UseUpdateReminderDaysReturn = UseMutationReturnType<
    AxiosResponse,
    AxiosError<ApiErrorResponse>,
    number,
    unknown
> & {
    errorMessage: ComputedRef<string | null>;
};

// Срок напоминаний на почту (1–7 дней до даты списка). Выбор применяется сразу,
// до ответа сервера; при ошибке возвращается прежнее значение, если пользователь
// после этого не выбрал другое
export const useUpdateReminderDays = (): UseUpdateReminderDaysReturn => {
    const authStore = useAuthStore();

    const setValue = (reminderDays: number): void => {
        if (authStore.user) {
            authStore.setUser({ ...authStore.user, reminderDays });
        }
    };

    const mutation = useMutation<
        AxiosResponse,
        AxiosError<ApiErrorResponse>,
        number,
        { previous: number | undefined }
    >({
        mutationFn: (reminderDays) => api.patch('/v1/user', { reminderDays }),

        onMutate: (reminderDays) => {
            const previous = authStore.user?.reminderDays;
            setValue(reminderDays);

            return { previous };
        },

        onError: (_error, reminderDays, context) => {
            if (
                context?.previous !== undefined &&
                authStore.user?.reminderDays === reminderDays
            ) {
                setValue(context.previous);
            }
        },
    });

    const errorMessage = computed((): string | null =>
        getApiErrorMessage(
            mutation.error.value,
            'Не удалось сохранить настройку',
        ),
    );

    return {
        ...mutation,
        errorMessage,
    };
};

type UseResetUserSettingsReturn = UseMutationReturnType<
    AxiosResponse<{ data: User }>,
    AxiosError<ApiErrorResponse>,
    void,
    unknown
> & {
    errorMessage: ComputedRef<string | null>;
};

// Возврат настроек аккаунта (фон, «Скоро у друзей», напоминания и их срок)
// к значениям по умолчанию. Значения знает только сервер, поэтому пользователь
// в сторе заменяется ответом, а не меняется заранее. Тему, которая хранится
// в браузере, сбрасывает вызывающий компонент
export const useResetUserSettings = (): UseResetUserSettingsReturn => {
    const authStore = useAuthStore();

    const mutation = useMutation<
        AxiosResponse<{ data: User }>,
        AxiosError<ApiErrorResponse>,
        void
    >({
        mutationFn: () => api.post('/v1/user/settings/reset'),

        onSuccess: (response) => {
            authStore.setUser(response.data.data);
        },
    });

    const errorMessage = computed((): string | null =>
        getApiErrorMessage(
            mutation.error.value,
            'Не удалось сбросить настройки',
        ),
    );

    return {
        ...mutation,
        errorMessage,
    };
};

type UseUploadAvatarReturn = UseMutationReturnType<
    AxiosResponse<{ data: User }>,
    AxiosError<ApiErrorResponse>,
    Blob,
    unknown
> & {
    errorMessage: ComputedRef<string | null>;
};

// Загрузка фотографии пользователя: отправляется уже кадрированное изображение.
// Сервер перекодирует его в WebP 256×256 и возвращает пользователя с новым avatarUrl
export const useUploadAvatar = (): UseUploadAvatarReturn => {
    const authStore = useAuthStore();

    const mutation = useMutation<
        AxiosResponse<{ data: User }>,
        AxiosError<ApiErrorResponse>,
        Blob
    >({
        mutationFn: (image) => {
            const formData = new FormData();
            formData.append('avatar', image, 'avatar.jpg');

            return api.post('/v1/user/avatar', formData);
        },

        onSuccess: (response) => {
            authStore.setUser(response.data.data);
        },
    });

    const errorMessage = computed((): string | null => {
        // 413 отдаёт nginx, если файл больше client_max_body_size; ответ не JSON
        if (mutation.error.value?.response?.status === 413) {
            return 'Файл слишком большой';
        }

        return getApiErrorMessage(
            mutation.error.value,
            'Не удалось загрузить фото. Попробуйте позже',
        );
    });

    return {
        ...mutation,
        errorMessage,
    };
};

type UseDeleteAvatarReturn = UseMutationReturnType<
    AxiosResponse<{ data: User }>,
    AxiosError<ApiErrorResponse>,
    void,
    unknown
> & {
    errorMessage: ComputedRef<string | null>;
};

// Удаление фотографии пользователя: вместо неё снова показывается первая буква имени
export const useDeleteAvatar = (): UseDeleteAvatarReturn => {
    const authStore = useAuthStore();

    const mutation = useMutation<
        AxiosResponse<{ data: User }>,
        AxiosError<ApiErrorResponse>,
        void
    >({
        mutationFn: () => api.delete('/v1/user/avatar'),

        onSuccess: (response) => {
            authStore.setUser(response.data.data);
        },
    });

    const errorMessage = computed((): string | null =>
        getApiErrorMessage(mutation.error.value, 'Не удалось удалить фото'),
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

    const data = error.response?.data?.data;

    // Для 429 лимитера маршрута обработчик исключений отдаёт английский
    // текст фреймворка. Свой текст и срок отдаёт лимит попыток входа
    // в аккаунт (AuthService::login)
    if (error.response?.status === 429) {
        return data?.message && data.message !== 'Too Many Attempts.'
            ? data.message
            : 'Слишком много попыток. Подождите минуту и попробуйте снова';
    }
    const firstFieldError = Object.values(data?.errors ?? {})[0]?.[0];

    return firstFieldError ?? data?.message ?? fallback;
}

export const useUser = (): UseQueryReturnType<User, Error> => {
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
