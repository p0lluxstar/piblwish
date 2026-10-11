import type { AppBackground } from '@/types/user';

// Фон приложения по умолчанию: у гостей и у пользователей, которые его не меняли
export const DEFAULT_APP_BACKGROUND: AppBackground = 'blossom';

// Фоны приложения в порядке вывода в настройках аккаунта.
// Цвета задаются в resources/scss/ui/appBackgrounds.scss
export const APP_BACKGROUNDS: { value: AppBackground; label: string }[] = [
    { value: 'blossom', label: 'Цветущий' },
    { value: 'ocean', label: 'Морской' },
    { value: 'mint', label: 'Мятный' },
    { value: 'sand', label: 'Песочный' },
    { value: 'mist', label: 'Туманный' },
    { value: 'stone', label: 'Серый' },
    { value: 'cobalt', label: 'Синий' },
];
