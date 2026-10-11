import { type Ref, ref } from 'vue';

import { api } from '@/lib/api';
import type { WishlistReservation } from '@/types/wishlist';

// Длина токена брони, совпадает с SharedWishlistService::RESERVATION_TOKEN_LENGTH
export const RESERVATION_TOKEN_LENGTH = 40;

// Сервер принимает не больше 20 токенов за запрос
const MAX_TOKENS = 20;

const storageKey = (wishlistId: string): string =>
    `wishlist-reservations:${wishlistId}`;

export const isReservationToken = (value: unknown): value is string =>
    typeof value === 'string' &&
    value.length === RESERVATION_TOKEN_LENGTH &&
    /^[A-Za-z0-9]+$/.test(value);

// Чтение и запись защищены: в приватном режиме доступ к localStorage может выбросить
// исключение, а сохранённое значение может оказаться повреждённым
const readTokens = (wishlistId: string): string[] => {
    try {
        const parsed: unknown = JSON.parse(
            window.localStorage.getItem(storageKey(wishlistId)) ?? '[]',
        );

        return Array.isArray(parsed) ? parsed.filter(isReservationToken) : [];
    } catch {
        return [];
    }
};

const writeTokens = (wishlistId: string, tokens: string[]): void => {
    try {
        if (tokens.length === 0) {
            window.localStorage.removeItem(storageKey(wishlistId));
        } else {
            window.localStorage.setItem(
                storageKey(wishlistId),
                JSON.stringify(tokens),
            );
        }
    } catch {
        // Сохранение недоступно — отменить выбор можно будет только по ссылке
    }
};

// Брони гостя на общей странице списка. Токены хранятся в localStorage этого браузера,
// поэтому гость видит свои подарки и может отменить их выбор без ввода кода
export const useGuestReservations = (
    wishlistId: string,
): {
    reservations: Ref<WishlistReservation[]>;
    tokenForItem: (itemId: string | undefined) => string | null;
    load: (extraToken?: string | null) => Promise<void>;
    remember: (reservation: WishlistReservation) => void;
} => {
    const reservations = ref<WishlistReservation[]>([]);

    const save = (): void => {
        writeTokens(
            wishlistId,
            reservations.value.map((reservation) => reservation.token),
        );
    };

    // Запоминает бронь после сохранения или отмены; бронь без позиций забывается
    const remember = (reservation: WishlistReservation): void => {
        const others = reservations.value.filter(
            (item) => item.token !== reservation.token,
        );

        reservations.value =
            reservation.itemIds.length > 0 ? [...others, reservation] : others;

        save();
    };

    // Загружает позиции броней по сохранённым токенам и токену из ссылки для отмены.
    // Токены, которых сервер не знает (бронь отменена или список изменён), удаляются
    const load = async (extraToken?: string | null): Promise<void> => {
        const tokens = [
            ...new Set([
                ...readTokens(wishlistId),
                ...(isReservationToken(extraToken) ? [extraToken] : []),
            ]),
        ].slice(-MAX_TOKENS);

        if (tokens.length === 0) return;

        try {
            const response = await api.post<{ data: WishlistReservation[] }>(
                `/api/v1/shared-wishlists/${wishlistId}/reservations/lookup`,
                { tokens },
            );

            reservations.value = response.data.data;
            save();
        } catch (error) {
            // Сохранённые токены не удаляются: ошибка может быть временной
            console.error('Ошибка загрузки броней:', error);
        }
    };

    const tokenForItem = (itemId: string | undefined): string | null => {
        if (!itemId) return null;

        return (
            reservations.value.find((reservation) =>
                reservation.itemIds.includes(itemId),
            )?.token ?? null
        );
    };

    return { reservations, tokenForItem, load, remember };
};
