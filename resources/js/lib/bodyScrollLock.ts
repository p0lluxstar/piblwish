// Позиция прокрутки страницы на момент открытия первого окна
let savedScrollY = 0;

// Блокировка прокрутки страницы под модальным окном (класс modal-open в app.css).
// Класс делает body position: fixed, и без сдвига страница показывалась бы
// с самого верха: поэтому body сдвигается вверх на текущую прокрутку.
// Повторный вызов из окна поверх другого окна ничего не меняет
export const lockBodyScroll = (): void => {
    const { body } = document;

    if (body.classList.contains('modal-open')) return;

    savedScrollY = window.scrollY;
    body.style.top = `-${savedScrollY}px`;
    body.classList.add('modal-open');
};

// Окна могут лежать друг на друге (подтверждение удаления поверх просмотра
// карточки), поэтому прокрутка возвращается, только когда закрылось последнее.
// Вызывается из onUnmounted: разметка закрытого окна к этому времени уже удалена
export const unlockBodyScroll = (): void => {
    const { body } = document;

    if (
        document.querySelector('.modal-overlay') ||
        !body.classList.contains('modal-open')
    ) {
        return;
    }

    body.classList.remove('modal-open');
    body.style.top = '';
    window.scrollTo({ top: savedScrollY, behavior: 'instant' });
};
