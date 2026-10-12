// Обработчик before-leave для TransitionGroup с сеткой карточек.
// Уходящая карточка выводится из потока с прежними координатами и размером:
// остальные карточки сразу занимают освободившееся место и плавно сдвигаются
// (класс *-move), а уходящая исчезает поверх них. Без этого место освобождалось
// бы только после окончания анимации и карточки перескакивали бы рывком.
// Контейнеру сетки нужен position: relative.
export const pinLeavingCard = (el: Element): void => {
    const card = el as HTMLElement;
    const { offsetLeft, offsetTop, offsetWidth, offsetHeight } = card;

    card.style.position = 'absolute';
    card.style.left = `${offsetLeft}px`;
    card.style.top = `${offsetTop}px`;
    card.style.width = `${offsetWidth}px`;
    card.style.height = `${offsetHeight}px`;
    card.style.margin = '0';
};
