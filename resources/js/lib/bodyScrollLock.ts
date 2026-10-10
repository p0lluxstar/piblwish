// Блокировка прокрутки страницы под модальным окном (класс modal-open в app.css)
export const lockBodyScroll = (): void => {
    document.body.classList.add('modal-open');
};

// Окна могут лежать друг на друге (подтверждение удаления поверх просмотра
// карточки), поэтому прокрутка возвращается, только когда закрылось последнее.
// Вызывается из onUnmounted: разметка закрытого окна к этому времени уже удалена
export const unlockBodyScroll = (): void => {
    if (!document.querySelector('.modal-overlay')) {
        document.body.classList.remove('modal-open');
    }
};
