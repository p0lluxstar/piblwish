import { computed, type ComputedRef, onMounted, onUnmounted, ref } from 'vue';

// Срок действия кода из письма, как на сервере (регистрация и
// восстановление пароля)
export const CODE_TTL_SECONDS = 120;

type UseCodeTimerReturn = {
    // Код истёк: поле ввода скрывается до отправки нового кода
    isExpired: ComputedRef<boolean>;
    // Оставшееся время в формате m:ss
    formattedTime: ComputedRef<string>;
    // Запускает отсчёт заново, например после отправки нового кода
    restart: () => void;
};

// Обратный отсчёт срока действия кода. Запускается при монтировании.
// Остаток считается от момента окончания срока, а не уменьшается
// на секунду за тик: в фоновой вкладке браузер вызывает интервал реже
export const useCodeTimer = (): UseCodeTimerReturn => {
    const timeLeft = ref(CODE_TTL_SECONDS);
    let timer: number | null = null;

    const isExpired = computed((): boolean => timeLeft.value <= 0);

    const formattedTime = computed((): string => {
        const minutes = Math.floor(timeLeft.value / 60);
        const seconds = timeLeft.value % 60;

        return `${minutes}:${seconds.toString().padStart(2, '0')}`;
    });

    const stop = (): void => {
        if (timer) {
            window.clearInterval(timer);
            timer = null;
        }
    };

    const restart = (): void => {
        stop();

        const expiresAt = Date.now() + CODE_TTL_SECONDS * 1000;
        timeLeft.value = CODE_TTL_SECONDS;

        timer = window.setInterval(() => {
            timeLeft.value = Math.max(
                0,
                Math.ceil((expiresAt - Date.now()) / 1000),
            );

            if (timeLeft.value <= 0) stop();
        }, 1000);
    };

    onMounted(restart);
    onUnmounted(stop);

    return { isExpired, formattedTime, restart };
};
