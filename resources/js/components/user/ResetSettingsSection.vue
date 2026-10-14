<script setup lang="ts">
import { ref } from 'vue';

import FormErrorMessage from '@/components/ui/FormErrorMessage.vue';
import LoaderButtonSpinner from '@/components/ui/LoaderButtonSpinner.vue';
import { useResetUserSettings } from '@/composables/useAuth';
import { useTheme } from '@/composables/useTheme';

const {
    mutate: resetSettings,
    isPending,
    isError,
    errorMessage,
    reset: resetMutation,
} = useResetUserSettings();

const { setPreference } = useTheme();

// Подтверждение показывается вместо кнопки, как у «Выйти везде»:
// сброс снова включает письма-напоминания, если их выключили
const isConfirmVisible = ref(false);

const showConfirm = (): void => {
    isConfirmVisible.value = true;
};

const hideConfirm = (): void => {
    isConfirmVisible.value = false;
    resetMutation();
};

const handleReset = (): void => {
    resetSettings(undefined, {
        onSuccess: () => {
            // Тема хранится в браузере, а не в аккаунте, поэтому сбрасывается
            // здесь и только на этом устройстве
            setPreference('system');
            isConfirmVisible.value = false;
        },
    });
};
</script>

<template>
    <section class="reset-section">
        <h3 class="section-title">Настройки по умолчанию</h3>

        <div v-if="!isConfirmVisible" class="account-current">
            <span class="account-value reset-hint">
                Фон, тема, дашборд и уведомления
            </span>

            <button type="button" class="change-btn" @click="showConfirm">
                Сбросить
            </button>
        </div>

        <div v-else>
            <p class="confirm-text">
                Вернуть настройки по умолчанию? Напоминания на почту снова
                включатся, тема станет «Как в системе».
            </p>

            <FormErrorMessage
                class="form-message"
                :show="isError"
                :message="errorMessage ?? ''"
            />

            <div class="form-actions">
                <button
                    type="button"
                    class="cancel-btn"
                    :disabled="isPending"
                    @click="hideConfirm"
                >
                    Отмена
                </button>

                <button
                    type="button"
                    class="create-btn"
                    :disabled="isPending"
                    @click="handleReset"
                >
                    <LoaderButtonSpinner v-if="isPending" :size="18" />

                    <span v-else>Сбросить</span>
                </button>
            </div>
        </div>
    </section>
</template>

<style scoped lang="scss">
@use '../../../scss/ui/createButton.scss';
@use '../../../scss/ui/accountSection.scss';

.reset-section {
    margin-top: 20px;
    padding-top: 18px;
    border-top: 1px dashed rgba(139, 92, 246, 0.2);
}

// Пояснение в строке с кнопкой переносится, а не обрезается
.reset-hint {
    white-space: normal;
    font-size: 13px;
    color: var(--ink-soft);
}

.confirm-text {
    margin: 0 0 14px;
    font-size: 13px;
    font-weight: 600;
    line-height: 1.5;
    color: var(--ink);
}
</style>
