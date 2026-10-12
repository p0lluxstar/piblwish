import { onMounted, onUnmounted, type Ref } from 'vue';

// Esc в модальном окне: обработчик срабатывает, только если окно верхнее.
// Поверх окна может быть открыто другое (например, подтверждение удаления
// поверх просмотра карточки), и тогда Esc относится к нему
export const useEscapeKey = (
    overlay: Readonly<Ref<HTMLElement | null>>,
    handler: () => void,
): void => {
    const handleKeydown = (event: KeyboardEvent): void => {
        if (event.key !== 'Escape' || event.isComposing) return;

        const overlays = document.querySelectorAll('.modal-overlay');

        if (overlays[overlays.length - 1] === overlay.value) {
            handler();
        }
    };

    onMounted(() => {
        window.addEventListener('keydown', handleKeydown);
    });

    onUnmounted(() => {
        window.removeEventListener('keydown', handleKeydown);
    });
};
