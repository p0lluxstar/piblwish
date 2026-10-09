import { type Ref, ref } from 'vue';

import { MOTIVATIONAL_PHRASES } from '@/constants/phrases';

// Фраза общая для шапки и дашборда: шапка её показывает, а дашборд меняет
// после действий пользователя, поэтому состояние хранится на уровне модуля
const phrase = ref('');

// Фраза меняется при открытии дашборда и после действий пользователя: «Обновить»,
// создание, изменение и удаление списка. Таймер не используется, чтобы движение
// в шапке не отвлекало. Подряд одна и та же фраза не выпадает
const generatePhrase = (): void => {
    const candidates = MOTIVATIONAL_PHRASES.filter(
        (candidate) => candidate !== phrase.value,
    );
    const pool = candidates.length > 0 ? candidates : MOTIVATIONAL_PHRASES;

    phrase.value = pool[Math.floor(Math.random() * pool.length)];
};

generatePhrase();

// Самая длинная фраза задаёт ширину блока с названием в шапке, чтобы при смене
// фразы соседние элементы не сдвигались
const longestPhrase = MOTIVATIONAL_PHRASES.reduce((longest, candidate) =>
    candidate.length > longest.length ? candidate : longest,
);

export function useMotivationalPhrase(): {
    phrase: Readonly<Ref<string>>;
    longestPhrase: string;
    generatePhrase: () => void;
} {
    return { phrase, longestPhrase, generatePhrase };
}
