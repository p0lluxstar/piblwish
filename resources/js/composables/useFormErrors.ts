import type { AxiosError } from 'axios';
import { computed, type ComputedRef, type Ref, ref, watch } from 'vue';

import type { FieldError } from '@/lib/authValidation';
import type { ApiErrorResponse } from '@/types/api';

type Options<Field extends string> = {
    // Поля формы: их названия совпадают с ключами errors в ответе сервера
    form: Record<Field, unknown>;
    serverError: Ref<AxiosError<ApiErrorResponse> | null>;
    serverMessage: Ref<string | null>;
    resetServerError: () => void;
};

type UseFormErrorsReturn<Field extends string> = {
    // Поле, которое подсвечивается как ошибочное
    invalidField: ComputedRef<Field | null>;
    // Текст ошибки под формой: сначала ошибка проверки на клиенте, затем ответ сервера
    message: ComputedRef<string | null>;
    // Запоминает результат проверки на клиенте; true, если ошибок нет
    // и запрос можно отправлять
    validate: (error: FieldError<Field> | null) => boolean;
};

// Ошибки формы входа или регистрации: проверка на клиенте и ошибки
// валидации сервера. Ошибка вместе с подсветкой поля снимается,
// как только пользователь начинает исправлять это поле
export const useFormErrors = <Field extends string>({
    form,
    serverError,
    serverMessage,
    resetServerError,
}: Options<Field>): UseFormErrorsReturn<Field> => {
    const validationError = ref<FieldError<Field> | null>(
        null,
    ) as Ref<FieldError<Field> | null>;

    // Первое поле из errors ответа сервера, если оно есть в форме
    const serverField = computed((): Field | null => {
        const errors = serverError.value?.response?.data?.data?.errors;
        const field = Object.keys(errors ?? {})[0];

        return field && field in form ? (field as Field) : null;
    });

    const invalidField = computed(
        (): Field | null => validationError.value?.field ?? serverField.value,
    );

    const message = computed(
        (): string | null =>
            validationError.value?.message ?? serverMessage.value,
    );

    watch(
        () => ({ ...form }),
        (current, previous) => {
            const field = invalidField.value;

            if (!field || current[field] === previous[field]) return;

            if (validationError.value) {
                validationError.value = null;
            } else {
                resetServerError();
            }
        },
    );

    const validate = (error: FieldError<Field> | null): boolean => {
        validationError.value = error;

        return error === null;
    };

    return { invalidField, message, validate };
};
