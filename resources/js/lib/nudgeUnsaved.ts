// Окно не закрылось кликом по фону из-за несохранённых правок: оно вздрагивает,
// а кнопка отправки формы прокручивается в видимую область и подсвечивается.
// Анимации заданы через Web Animations API: CSS-класс с анимацией на .modal
// при снятии заново запустил бы анимацию появления окна. При
// prefers-reduced-motion окно не трясётся, остаётся только подсветка кнопки
export const nudgeUnsaved = (
    modal: HTMLElement | null,
    submitButton: HTMLButtonElement | null,
): void => {
    const reduceMotion = window.matchMedia(
        '(prefers-reduced-motion: reduce)',
    ).matches;

    if (!reduceMotion) {
        modal?.animate(
            [
                { transform: 'translateX(0)' },
                { transform: 'translateX(-8px)' },
                { transform: 'translateX(7px)' },
                { transform: 'translateX(-5px)' },
                { transform: 'translateX(3px)' },
                { transform: 'translateX(0)' },
            ],
            { duration: 400, easing: 'ease-in-out' },
        );
    }

    submitButton?.scrollIntoView({
        block: 'nearest',
        behavior: reduceMotion ? 'auto' : 'smooth',
    });

    submitButton?.animate(
        [
            { boxShadow: '0 0 0 0 rgba(139, 92, 246, 0.6)' },
            { boxShadow: '0 0 0 10px rgba(139, 92, 246, 0)' },
        ],
        { duration: 800, easing: 'ease-out', iterations: 2 },
    );
};
