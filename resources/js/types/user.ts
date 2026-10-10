// Ключ фона приложения, совпадает с App\Enums\AppBackground на бэкенде
export type AppBackground =
    | 'blossom'
    | 'ocean'
    | 'mint'
    | 'sand'
    | 'mist'
    | 'stone'
    | 'cobalt';

export type User = {
    id: number;
    email: string;
    username?: string;
    background: AppBackground;
    // Адрес фотографии; null — фотографии нет, показывается первая буква имени
    avatarUrl: string | null;
    // Дата регистрации в ISO 8601
    createdAt: string | null;
};
