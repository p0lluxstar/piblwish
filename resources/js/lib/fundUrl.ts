import { FUND_HOSTS, FUND_PLATFORMS_TEXT } from '../constants/fundHosts';
import { isValidItemUrl, normalizeItemUrl } from './itemUrl';

// Название платформы сбора по ссылке («Т-Банк»); null — ссылка не https
// или ведёт на сайт не из списка. Хост сравнивается целиком или с точкой
// перед доменом, поэтому evilcloudtips.ru не считается CloudTips.
// Ссылка должна быть уже нормализована (normalizeItemUrl)
export const getFundPlatform = (url: string): string | null => {
    if (!isValidItemUrl(url)) return null;

    const parsed = new window.URL(url);

    if (parsed.protocol !== 'https:') return null;

    const host = parsed.hostname.toLowerCase().replace(/\.$/, '');

    const match = Object.keys(FUND_HOSTS).find(
        (allowed) => host === allowed || host.endsWith(`.${allowed}`),
    );

    return match ? FUND_HOSTS[match] : null;
};

// Платформа по введённому в поле значению: адрес без схемы дополняется https://
export const getFundPlatformOfInput = (
    value: string | null | undefined,
): string | null => {
    const url = normalizeItemUrl(value);

    return url ? getFundPlatform(url) : null;
};

// Ошибка поля ссылки цели сбора; null — ссылка подходит. Ссылка у цели обязательна
export const getFundUrlError = (
    value: string | null | undefined,
): string | null => {
    if (!normalizeItemUrl(value)) return 'Укажите ссылку на сбор';

    return getFundPlatformOfInput(value)
        ? null
        : `Ссылки на сбор принимаются только с сайтов: ${FUND_PLATFORMS_TEXT}`;
};
