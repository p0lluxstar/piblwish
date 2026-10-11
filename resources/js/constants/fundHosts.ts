// Платформы сборов: домены, ссылки на которые принимаются в позициях сбора,
// и названия для подписи ссылки. Подходят и поддомены (pay.cloudtips.ru).
// Совпадает с fund_hosts в config/wishlist.php: окончательно ссылку проверяет сервер
export const FUND_HOSTS: Readonly<Record<string, string>> = {
    'tbank.ru': 'Т-Банк',
    'tinkoff.ru': 'Т-Банк',
    'yoomoney.ru': 'ЮMoney',
    'cloudtips.ru': 'CloudTips',
};

// «Т-Банк, ЮMoney, CloudTips»: подсказка под полем и текст ошибки
export const FUND_PLATFORMS_TEXT = [...new Set(Object.values(FUND_HOSTS))].join(
    ', ',
);
