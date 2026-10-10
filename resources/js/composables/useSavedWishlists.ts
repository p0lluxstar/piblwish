import {
    useMutation,
    type UseMutationReturnType,
    useQuery,
    useQueryClient,
    type UseQueryReturnType,
} from '@tanstack/vue-query';
import type { AxiosError } from 'axios';
import {
    computed,
    type ComputedRef,
    type MaybeRefOrGetter,
    toValue,
} from 'vue';

import { api } from '@/lib/api';
import { useAuthStore } from '@/stores/auth';
import type { ApiErrorResponse } from '@/types/api';
import type { SavedWishlist } from '@/types/savedWishlist';

// Ключ включает id пользователя: после выхода и входа под другим аккаунтом
// закладки прежнего пользователя не показываются даже из кэша
const savedWishlistsKey = (userId: string | number | undefined): unknown[] => [
    'saved-wishlists',
    userId ?? null,
];

// Чужие списки вошедшего пользователя; без входа запрос не выполняется
export const useSavedWishlists = (
    enabled: MaybeRefOrGetter<boolean> = true,
): UseQueryReturnType<SavedWishlist[], AxiosError<ApiErrorResponse>> => {
    const auth = useAuthStore();

    return useQuery<SavedWishlist[], AxiosError<ApiErrorResponse>>({
        queryKey: computed(() => savedWishlistsKey(auth.user?.id)),
        queryFn: async () => {
            const response = await api.get<{ data: SavedWishlist[] }>(
                '/v1/saved-wishlists',
            );

            return response.data.data;
        },
        enabled: computed(() => Boolean(auth.user) && toValue(enabled)),
    });
};

type ToggleSavedVariables = { id: string; save: boolean };

type UseToggleSavedWishlistReturn = UseMutationReturnType<
    SavedWishlist | null,
    AxiosError<ApiErrorResponse>,
    ToggleSavedVariables,
    unknown
> & {
    errorMessage: ComputedRef<string | null>;
};

// Добавить список к себе (save = true) или убрать его из чужих списков.
// Кэш раздела обновляется по ответу, без повторного запроса списка
export const useToggleSavedWishlist = (): UseToggleSavedWishlistReturn => {
    const auth = useAuthStore();
    const queryClient = useQueryClient();

    const mutation = useMutation<
        SavedWishlist | null,
        AxiosError<ApiErrorResponse>,
        ToggleSavedVariables
    >({
        mutationFn: async ({ id, save }) => {
            if (!save) {
                await api.delete(`/v1/saved-wishlists/${id}`);

                return null;
            }

            const response = await api.post<{ data: SavedWishlist }>(
                `/v1/saved-wishlists/${id}`,
            );

            return response.data.data;
        },

        onSuccess: (saved, { id }) => {
            queryClient.setQueryData<SavedWishlist[]>(
                savedWishlistsKey(auth.user?.id),
                (list) => {
                    if (!list) return list;

                    const rest = list.filter((item) => item.id !== id);

                    return saved ? [saved, ...rest] : rest;
                },
            );
        },
    });

    const errorMessage = computed((): string | null => {
        const error = mutation.error.value;

        if (!error) return null;

        if (error.response?.status === 429) {
            return 'Слишком много попыток. Подождите минуту и попробуйте снова';
        }

        return (
            error.response?.data?.data?.message ??
            'Не удалось сохранить изменения. Попробуйте ещё раз'
        );
    });

    return {
        ...mutation,
        errorMessage,
    };
};
