// Стоимость позиции списка в целых рублях. Бэкенд принимает от 0 до 10 000 000,
// а целевую сумму сбора — до 100 000 000
export const MAX_ITEM_PRICE = 10_000_000;

export const MAX_FUND_TARGET = 100_000_000;

// Форматтеры создаются один раз: Intl.NumberFormat дорог в создании,
// а цены выводятся у каждой позиции
const priceFormatter = new Intl.NumberFormat('ru-RU', {
    style: 'currency',
    currency: 'RUB',
    maximumFractionDigits: 0,
});

const digitsFormatter = new Intl.NumberFormat('ru-RU', {
    maximumFractionDigits: 0,
});

// Цена для подписи позиции: 1500 → «1 500 ₽»
export const formatPrice = (value: number): string =>
    priceFormatter.format(value);

// Цена в поле ввода, где знак ₽ стоит отдельно: 1500 → «1 500»
export const formatPriceDigits = (value: number): string =>
    digitsFormatter.format(value);

// Разбирает введённую стоимость: копейки после запятой или точки отбрасываются,
// пробелы и прочие символы игнорируются, значение ограничивается максимумом.
// Пустой ввод — null, то есть стоимость не указана
export const parsePriceInput = (
    value: string,
    max = MAX_ITEM_PRICE,
): number | null => {
    const digits = value.split(/[.,]/)[0].replace(/\D/g, '');

    if (!digits) return null;

    return Math.min(Number(digits), max);
};
