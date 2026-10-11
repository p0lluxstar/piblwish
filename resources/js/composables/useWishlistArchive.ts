import {
    skipToken,
    useMutation,
    useQuery,
    useQueryClient,
    type UseQueryReturnType,
} from '@tanstack/vue-query';
import type { AxiosError } from 'axios';
import { computed, type ComputedRef, type Ref } from 'vue';

import { api } from '@/lib/api';
import type { ApiErrorResponse } from '@/types/api';
import type { Wishlist } from '@/types/wishlist';

// Активные списки пользователя. Ключ архива начинается с него же, поэтому
// invalidateQueries({ queryKey: ['wishlists'] }) обновляет оба раздела
export const WISHLISTS_KEY = ['wishlists'];
export const ARCHIVED_WISHLISTS_KEY = ['wishlists', 'archived'];
// Число списков в архиве для ссылки «Архив». Его присылает тот же запрос
// списков (meta.archivedCount), отдельного запроса нет
const ARCHIVED_COUNT_KEY = ['wishlists-archived-count'];

type WishlistsResponse = {
    data: Wishlist[];
    meta?: { archivedCount?: number };
};

// Активные списки пользователя или его архив ($archived)
export const useUserWishlists = (
    archived: boolean,
): UseQueryReturnType<Wishlist[], AxiosError<ApiErrorResponse>> => {
    const queryClient = useQueryClient();

    return useQuery<Wishlist[], AxiosError<ApiErrorResponse>>({
        queryKey: archived ? ARCHIVED_WISHLISTS_KEY : WISHLISTS_KEY,
        queryFn: async () => {
            const response = await api.get<WishlistsResponse>('/v1/wishlists', {
                params: archived ? { archived: 1 } : undefined,
            });

            queryClient.setQueryData(
                ARCHIVED_COUNT_KEY,
                response.data.meta?.archivedCount ?? 0,
            );

            return response.data.data;
        },
    });
};

// Число списков в архиве: обновляется при каждой загрузке списков
// и сразу при переносе в архив или восстановлении
export const useArchivedCount = (): ComputedRef<number> => {
    const { data } = useQuery<number>({
        queryKey: ARCHIVED_COUNT_KEY,
        queryFn: skipToken,
    });

    return computed(() => data.value ?? 0);
};

type MoveVariables = {
    wishlist: Wishlist;
    // true — перенести в архив, false — восстановить
    archive: boolean;
};

// Кэш до переноса: при ошибке он возвращается
type MoveContext = {
    active: Wishlist[] | undefined;
    archived: Wishlist[] | undefined;
    count: number | undefined;
};

type MoveCallbacks = {
    onSuccess?: () => void;
    onError?: (error: AxiosError<ApiErrorResponse>) => void;
};

// Перенос списка в архив и восстановление из архива.
//
// Карточка сразу переносится между кэшами разделов, не дожидаясь ответа сервера,
// и раздел не перезагружается. При ошибке кэш возвращается к прежнему состоянию.
// Переносы выполняются по очереди (scope): «Отменить» сразу после переноса
// не должен дойти до сервера раньше самого переноса
export const useWishlistArchive = (): {
    moveWishlist: (
        wishlist: Wishlist,
        archive: boolean,
        callbacks?: MoveCallbacks,
    ) => void;
} => {
    const queryClient = useQueryClient();

    const withoutWishlist = (
        wishlists: Wishlist[] | undefined,
        id: string,
    ): Wishlist[] | undefined =>
        wishlists?.filter((wishlist) => wishlist.id !== id);

    const { mutate } = useMutation<
        Wishlist,
        AxiosError<ApiErrorResponse>,
        MoveVariables,
        MoveContext
    >({
        scope: { id: 'wishlist-archive' },

        mutationFn: async ({ wishlist, archive }) => {
            const url = `/v1/wishlists/${wishlist.id}/archive`;
            const response = archive
                ? await api.post<{ data: Wishlist }>(url)
                : await api.delete<{ data: Wishlist }>(url);

            return response.data.data;
        },

        onMutate: async ({ wishlist, archive }) => {
            // Ответ на запрос, начатый до переноса, вернул бы карточку на место
            await queryClient.cancelQueries({ queryKey: WISHLISTS_KEY });

            const context: MoveContext = {
                active: queryClient.getQueryData(WISHLISTS_KEY),
                archived: queryClient.getQueryData(ARCHIVED_WISHLISTS_KEY),
                count: queryClient.getQueryData(ARCHIVED_COUNT_KEY),
            };

            const [fromKey, toKey] = archive
                ? [WISHLISTS_KEY, ARCHIVED_WISHLISTS_KEY]
                : [ARCHIVED_WISHLISTS_KEY, WISHLISTS_KEY];

            const moved: Wishlist = {
                ...wishlist,
                archivedAt: archive ? new Date().toISOString() : null,
            };

            queryClient.setQueryData<Wishlist[]>(fromKey, (old) =>
                withoutWishlist(old, wishlist.id),
            );

            // Раздел, который ещё не загружался, загрузится целиком при открытии
            queryClient.setQueryData<Wishlist[]>(toKey, (old) =>
                old
                    ? [moved, ...(withoutWishlist(old, wishlist.id) ?? [])]
                    : old,
            );

            queryClient.setQueryData<number>(ARCHIVED_COUNT_KEY, (old) =>
                Math.max((old ?? 0) + (archive ? 1 : -1), 0),
            );

            return context;
        },

        // Время переноса в архив берётся из ответа сервера
        onSuccess: (saved, { archive }) => {
            queryClient.setQueryData<Wishlist[]>(
                archive ? ARCHIVED_WISHLISTS_KEY : WISHLISTS_KEY,
                (old) =>
                    old?.map((wishlist) =>
                        wishlist.id === saved.id ? saved : wishlist,
                    ),
            );
        },

        onError: (error, _variables, context) => {
            console.error('Ошибка переноса списка в архив', error);

            if (!context) return;

            queryClient.setQueryData(WISHLISTS_KEY, context.active);
            queryClient.setQueryData(ARCHIVED_WISHLISTS_KEY, context.archived);
            queryClient.setQueryData(ARCHIVED_COUNT_KEY, context.count ?? 0);
        },
    });

    const moveWishlist = (
        wishlist: Wishlist,
        archive: boolean,
        callbacks: MoveCallbacks = {},
    ): void => {
        mutate({ wishlist, archive }, callbacks);
    };

    return { moveWishlist };
};

type DeleteArchivedResponse = {
    data: { deletedCount: number };
};

// Удаление всех карточек архива. Передаются id карточек, которые владелец видел
// в архиве: карточку, перенесённую в архив позже, сервер не удалит. Удалённые
// карточки убираются из кэша архива без его перезагрузки
export const useDeleteArchivedWishlists = (): {
    deleteArchived: (ids: string[], callbacks?: MoveCallbacks) => void;
    isPending: Ref<boolean>;
} => {
    const queryClient = useQueryClient();

    const { mutate, isPending } = useMutation<
        number,
        AxiosError<ApiErrorResponse>,
        string[]
    >({
        // Та же очередь, что и у переноса: восстановление, начатое раньше,
        // доходит до сервера до удаления
        scope: { id: 'wishlist-archive' },

        mutationFn: async (ids) => {
            const response = await api.delete<DeleteArchivedResponse>(
                '/v1/wishlists/archived',
                { data: { ids } },
            );

            return response.data.data.deletedCount;
        },

        onSuccess: (deletedCount, ids) => {
            const deleted = new Set(ids);

            queryClient.setQueryData<Wishlist[]>(
                ARCHIVED_WISHLISTS_KEY,
                (old) => old?.filter((wishlist) => !deleted.has(wishlist.id)),
            );

            queryClient.setQueryData<number>(ARCHIVED_COUNT_KEY, (old) =>
                Math.max((old ?? 0) - deletedCount, 0),
            );
        },

        onError: (error) => {
            console.error('Ошибка удаления архива', error);
        },
    });

    const deleteArchived = (
        ids: string[],
        callbacks: MoveCallbacks = {},
    ): void => {
        mutate(ids, callbacks);
    };

    return { deleteArchived, isPending };
};
