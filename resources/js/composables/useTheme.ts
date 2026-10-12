import { computed, type ComputedRef, type Ref, ref } from 'vue';

// Тема оформления. system — как в настройках устройства
export type ThemePreference = 'light' | 'dark' | 'system';
export type Theme = 'light' | 'dark';

// Ключ в localStorage. Его же читает скрипт в welcome.blade.php, который
// ставит тему до загрузки приложения, чтобы страница не мигала светлой
const STORAGE_KEY = 'app-theme';

export const THEME_OPTIONS: { value: ThemePreference; label: string }[] = [
    { value: 'light', label: 'Светлая' },
    { value: 'dark', label: 'Тёмная' },
    { value: 'system', label: 'Как в системе' },
];

const isPreference = (value: unknown): value is ThemePreference =>
    value === 'light' || value === 'dark' || value === 'system';

const readPreference = (): ThemePreference => {
    try {
        const stored = localStorage.getItem(STORAGE_KEY);

        return isPreference(stored) ? stored : 'system';
    } catch {
        return 'system';
    }
};

const media = window.matchMedia('(prefers-color-scheme: dark)');

// Состояние общее для всего приложения: тема хранится в браузере,
// а не в аккаунте, поэтому действует и у гостя
const preference: Ref<ThemePreference> = ref(readPreference());
const systemDark = ref(media.matches);

const theme: ComputedRef<Theme> = computed((): Theme => {
    if (preference.value === 'system') {
        return systemDark.value ? 'dark' : 'light';
    }

    return preference.value;
});

const apply = (): void => {
    document.documentElement.dataset.theme = theme.value;
};

media.addEventListener('change', (event: MediaQueryListEvent): void => {
    systemDark.value = event.matches;
    apply();
});

// Тема, выбранная в другой вкладке
window.addEventListener('storage', (event: StorageEvent): void => {
    if (event.key !== STORAGE_KEY) return;

    preference.value = isPreference(event.newValue) ? event.newValue : 'system';
    apply();
});

apply();

export function useTheme(): {
    preference: Ref<ThemePreference>;
    theme: ComputedRef<Theme>;
    setPreference: (value: ThemePreference) => void;
    toggleTheme: () => void;
} {
    const setPreference = (value: ThemePreference): void => {
        preference.value = value;

        try {
            localStorage.setItem(STORAGE_KEY, value);
        } catch {
            // Без localStorage тема действует до перезагрузки страницы
        }

        apply();
    };

    // Быстрое переключение из шапки: на противоположную текущей
    const toggleTheme = (): void => {
        setPreference(theme.value === 'dark' ? 'light' : 'dark');
    };

    return { preference, theme, setPreference, toggleTheme };
}
