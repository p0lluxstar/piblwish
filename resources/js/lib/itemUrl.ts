// Ссылки на товар у позиции списка. Бэкенд принимает не больше трёх ссылок,
// только http(s) длиной до 2048 символов
const MAX_URL_LENGTH = 2048;

export const MAX_ITEM_URLS = 3;

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

// Ссылки позиции для отправки: нормализованы, пустые поля отброшены
export const normalizeItemUrls = (
    values: readonly string[] | null | undefined,
): string[] =>
    (values ?? [])
        .map(normalizeItemUrl)
        .filter((url): url is string => url !== null);

// Ошибки полей ссылок позиции: true у поля с некорректной ссылкой, пустое поле не ошибка
export const getItemUrlErrors = (
    values: readonly string[] | null | undefined,
): boolean[] =>
    (values ?? []).map((value) => {
        const url = normalizeItemUrl(value);

        return Boolean(url && !isValidItemUrl(url));
    });

// Домен для подписи ссылки: «ozon.ru» вместо полного адреса
export const getItemUrlHost = (url: string): string => {
    try {
        return new window.URL(url).hostname.replace(/^www\./, '');
    } catch {
        return url;
    }
};

// Подпись ссылки на общей странице: домен длиннее maxLength символов
// обрезается многоточием, чтобы несколько ссылок помещались в одну строку
export const getItemUrlShortHost = (url: string, maxLength = 7): string => {
    const host = getItemUrlHost(url);

    return host.length > maxLength ? `${host.slice(0, maxLength)}…` : host;
};
