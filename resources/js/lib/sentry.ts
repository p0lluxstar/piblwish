import * as Sentry from '@sentry/vue';
import { isAxiosError } from 'axios';
import type { Pinia } from 'pinia';
import { type App, watch } from 'vue';
import type { Router } from 'vue-router';

import { useAuthStore } from '@/stores/auth';

// Доля переходов и запросов, для которых собирается трассировка: её хватает,
// чтобы связать ошибку с запросом к API, и квота спанов не расходуется зря
const DEFAULT_TRACES_SAMPLE_RATE = 0.1;

// Токен общей ссылки открывает список, поэтому в Sentry он не уходит:
// /shared-wishlist/<токен>, /api/v1/shared-wishlists/<токен> и закладки
// /v1/saved-wishlists/<токен> → :token
const SHARE_TOKEN_PATTERN =
    /(\/(?:shared-wishlists?|saved-wishlists)\/)[^/?#\s"]+/gi;

const scrubString = (value: string): string =>
    value.replace(SHARE_TOKEN_PATTERN, '$1:token');

// Обходит событие или спан целиком: адреса встречаются в request,
// breadcrumbs, названиях и атрибутах спанов
const scrub = <T>(value: T, depth = 0): T => {
    if (typeof value === 'string') {
        return scrubString(value) as T;
    }

    if (depth > 8 || value === null || typeof value !== 'object') {
        return value;
    }

    if (Array.isArray(value)) {
        return value.map((item) => scrub(item, depth + 1)) as T;
    }

    for (const key of Object.keys(value)) {
        const record = value as Record<string, unknown>;
        record[key] = scrub(record[key], depth + 1);
    }

    return value;
};

// Ошибки запросов к API не отправляются: 4xx (валидация, нет доступа,
// лимиты) — штатные ответы, 5xx бэкенд отправляет в Sentry сам, а обрывы
// сети и отменённые запросы зависят от соединения пользователя, а не от кода
const isApiError = (error: unknown): boolean => isAxiosError(error);

const apiOrigin = (): string | null => {
    try {
        return new URL(import.meta.env.VITE_API_URL).origin;
    } catch {
        return null;
    }
};

const sampleRate = (): number => {
    const value = Number(import.meta.env.VITE_SENTRY_TRACES_SAMPLE_RATE);

    return Number.isFinite(value) && value >= 0 && value <= 1
        ? value
        : DEFAULT_TRACES_SAMPLE_RATE;
};

// Без VITE_SENTRY_DSN Sentry выключен: локально DSN указывают только
// на время проверки, чтобы ошибки разработки не расходовали квоту
export function setupSentry(app: App, router: Router, pinia: Pinia): void {
    const dsn = import.meta.env.VITE_SENTRY_DSN;

    if (!dsn) {
        return;
    }

    const origin = apiOrigin();

    Sentry.init({
        app,
        dsn,
        environment:
            import.meta.env.VITE_SENTRY_ENVIRONMENT ||
            (import.meta.env.DEV ? 'local' : 'production'),
        // По умолчанию SDK собирает тела запросов и ответов (пароль при входе,
        // содержимое списков), заголовки и cookie. Ничего из этого не нужно для
        // разбора ошибок; о пользователе отправляется только ID (setUser ниже)
        dataCollection: {
            userInfo: false,
            cookies: false,
            httpHeaders: false,
            httpBodies: [],
        },
        integrations: [Sentry.browserTracingIntegration({ router })],
        tracesSampleRate: sampleRate(),
        // Заголовки трассировки получают только запросы к своему API
        tracePropagationTargets: origin ? [origin] : [],
        ignoreErrors: [
            'ResizeObserver loop limit exceeded',
            'ResizeObserver loop completed with undelivered notifications',
            'Non-Error promise rejection captured',
        ],
        denyUrls: [
            /extensions\//i,
            /^chrome(-extension)?:\/\//i,
            /^moz-extension:\/\//i,
            /^safari(-web)?-extension:\/\//i,
        ],
        beforeSend(event, hint) {
            if (isApiError(hint.originalException)) {
                return null;
            }

            return scrub(event);
        },
        // Трассировка в SDK 11 отправляется потоком спанов, и
        // beforeSendTransaction не вызывается: адреса чистятся в каждом спане
        beforeSendSpan(span) {
            return scrub(span);
        },
    });

    const auth = useAuthStore(pinia);

    watch(
        () => auth.user?.id,
        (id) => Sentry.setUser(id ? { id: String(id) } : null),
        { immediate: true },
    );
}
