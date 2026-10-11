// Проверка форм входа и регистрации до отправки. Правила повторяют
// LoginRequest, RegistrationRequest и VerifyRegistrationCodeRequest на бэкенде,
// поэтому при изменении правил там нужно поправить и здесь.
// Каждая функция возвращает первую ошибку в порядке полей формы или null
import type { LoginPayload, RegisterPayload } from '@/types/auth';

export const USERNAME_MIN_LENGTH = 3;
export const USERNAME_MAX_LENGTH = 30;
export const EMAIL_MAX_LENGTH = 255;
export const PASSWORD_MIN_LENGTH = 8;
// Минимум из LoginRequest
export const LOGIN_PASSWORD_MIN_LENGTH = 6;

// Ошибка с полем, которое её вызвало: поле подсвечивается в форме.
// Названия полей совпадают с ключами запроса и ключами errors в ответе сервера
export type FieldError<Field extends string> = {
    field: Field;
    message: string;
};

// Как правило alpha_dash в Laravel: буквы любого алфавита, цифры, дефис и подчёркивание
const USERNAME_PATTERN = /^[\p{L}\p{M}\p{N}_-]+$/u;
// Нестрогая проверка: точный формат проверяет сервер
const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const VERIFICATION_CODE_PATTERN = /^\d{6}$/;

// Laravel считает длину строки в символах (mb_strlen), а не в UTF-16
const length = (value: string): number => [...value].length;

// TrimStrings на бэкенде обрезает пробелы у всех полей, кроме паролей
const validateEmail = (value: string): string | null => {
    const email = value.trim();

    if (!email) return 'Email обязателен';
    if (length(email) > EMAIL_MAX_LENGTH) {
        return `Email не должен быть длиннее ${EMAIL_MAX_LENGTH} символов`;
    }
    if (!EMAIL_PATTERN.test(email)) return 'Введите корректный email';

    return null;
};

const validateUsername = (value: string): string | null => {
    const username = value.trim();

    if (!username) return 'Логин обязателен';
    if (length(username) < USERNAME_MIN_LENGTH) {
        return `Логин должен быть не короче ${USERNAME_MIN_LENGTH} символов`;
    }
    if (length(username) > USERNAME_MAX_LENGTH) {
        return `Логин не должен быть длиннее ${USERNAME_MAX_LENGTH} символов`;
    }
    if (!USERNAME_PATTERN.test(username)) {
        return 'Логин может содержать только буквы, цифры, дефисы и подчеркивания';
    }

    return null;
};

const validatePassword = (value: string, minLength: number): string | null => {
    if (!value) return 'Пароль обязателен';
    if (length(value) < minLength) {
        return `Пароль должен быть минимум ${minLength} символов`;
    }

    return null;
};

// Первая ошибка из списка проверок в порядке полей формы
const firstError = <Field extends string>(
    checks: [Field, string | null][],
): FieldError<Field> | null => {
    const failed = checks.find(([, message]) => message !== null);

    return failed ? { field: failed[0], message: failed[1] as string } : null;
};

export type LoginField = 'email' | 'password';

export const validateLogin = (
    payload: LoginPayload,
): FieldError<LoginField> | null =>
    firstError<LoginField>([
        ['email', validateEmail(payload.email)],
        [
            'password',
            validatePassword(payload.password, LOGIN_PASSWORD_MIN_LENGTH),
        ],
    ]);

export type RegisterField = keyof RegisterPayload;

export const validateRegistration = (
    payload: RegisterPayload,
): FieldError<RegisterField> | null =>
    firstError<RegisterField>([
        ['username', validateUsername(payload.username)],
        ['email', validateEmail(payload.email)],
        ['password', validatePassword(payload.password, PASSWORD_MIN_LENGTH)],
        [
            'password_confirmation',
            payload.password === payload.password_confirmation
                ? null
                : 'Пароли не совпадают',
        ],
    ]);

export const validateVerificationCode = (
    code: string,
): FieldError<'code'> | null => {
    const value = code.trim();

    if (!value) return { field: 'code', message: 'Код обязателен' };
    if (!VERIFICATION_CODE_PATTERN.test(value)) {
        return { field: 'code', message: 'Код должен состоять из 6 цифр' };
    }

    return null;
};
