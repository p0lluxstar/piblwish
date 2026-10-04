import { nextTick, type Ref, toRaw } from 'vue';

// Устойчивые ключи для v-for: с ключом-индексом при перестановке Vue оставляет
// DOM-элементы на месте и меняет только содержимое, из-за чего теряется фокус.
// Ключ привязан к самому объекту позиции, поэтому на сервер не отправляется
const keys = new WeakMap<object, string>();
let lastKey = 0;

const itemKey = (item: object): string => {
    const raw = toRaw(item);
    let key = keys.get(raw);

    if (!key) {
        key = `item-${++lastKey}`;
        keys.set(raw, key);
    }

    return key;
};

const swap = <T>(list: T[], a: number, b: number): void => {
    [list[a], list[b]] = [list[b], list[a]];
};

// Перестановка позиций в модалках создания и редактирования списка кнопками «вверх» и «вниз»
export const useItemReorder = <T extends object>(
    items: () => T[],
    urlErrors: Ref<boolean[]>,
): {
    itemKey: (item: object) => string;
    moveItem: (index: number, delta: -1 | 1, event: MouseEvent) => void;
} => {
    const moveItem = (
        index: number,
        delta: -1 | 1,
        event: MouseEvent,
    ): void => {
        const list = items();
        const target = index + delta;

        if (target < 0 || target >= list.length) return;

        swap(list, index, target);
        // Ошибки ссылок хранятся по индексам и переставляются вместе с позициями
        swap(urlErrors.value, index, target);

        // Браузер снимает фокус с элемента, который переместили в DOM.
        // Фокус возвращается на нажатую кнопку, а если она стала недоступна
        // (позиция дошла до края списка) — на соседнюю кнопку перестановки
        const button = event.currentTarget;

        if (!(button instanceof window.HTMLButtonElement)) return;

        void nextTick(() => {
            if (!button.disabled) {
                button.focus();

                return;
            }

            const sibling =
                button.parentElement?.querySelector<typeof button>(
                    'button:not(:disabled)',
                );

            sibling?.focus();
        });
    };

    return { itemKey, moveItem };
};
