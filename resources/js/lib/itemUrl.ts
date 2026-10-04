// Ссылка на товар у позиции списка. Бэкенд принимает только http(s) длиной до 2048 символов
const MAX_URL_LENGTH = 2048;

// Приводит введённую ссылку к виду для отправки: пустая строка — null,
// адрес без схемы (ozon.ru/…) дополняется https://
export const normalizeItemUrl = (
    value: string | null | undefined,
): string | null => {
    const trimmed = (value ?? '').trim();

    if (!trimmed) return null;

    return /^[a-z][a-z\d+.-]*:/i.test(trimmed) ? trimmed : `https://${trimmed}`;
};

// Проверяет уже нормализованную ссылку
export const isValidItemUrl = (url: string): boolean => {
    if (url.length > MAX_URL_LENGTH) return false;

    try {
        const parsed = new window.URL(url);

        return (
            ['http:', 'https:'].includes(parsed.protocol) &&
            parsed.hostname.includes('.')
        );
    } catch {
        return false;
    }
};

// Домен для подписи ссылки: «ozon.ru» вместо полного адреса
export const getItemUrlHost = (url: string): string => {
    try {
        return new window.URL(url).hostname.replace(/^www\./, '');
    } catch {
        return url;
    }
};
