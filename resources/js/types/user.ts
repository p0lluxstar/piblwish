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
};
