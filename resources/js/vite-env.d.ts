/// <reference types="vite/client" />

interface ImportMetaEnv {
    readonly VITE_API_URL: string;
    // Sentry выключен, если DSN не задан
    readonly VITE_SENTRY_DSN?: string;
    // По умолчанию local при разработке и production в сборке
    readonly VITE_SENTRY_ENVIRONMENT?: string;
    // Доля трассировки от 0 до 1, по умолчанию 0.1
    readonly VITE_SENTRY_TRACES_SAMPLE_RATE?: string;
}

interface ImportMeta {
    readonly env: ImportMetaEnv;
}
