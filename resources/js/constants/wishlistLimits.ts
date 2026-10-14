// Наибольшее число позиций в списке; совпадает с max_items в config/wishlist.php
export const MAX_WISHLIST_ITEMS = 100;

// Наибольшее число карточек у пользователя вместе с архивом; совпадает
// с max_wishlists в config/wishlist.php. Дашборд по нему отключает создание
// карточек, справка называет это число
export const MAX_WISHLISTS = 100;

// С этого числа карточек дашборд показывает, сколько их из MAX_WISHLISTS
export const WISHLISTS_WARNING_THRESHOLD = 90;
