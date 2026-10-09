<script setup lang="ts">
import { ref } from 'vue';

import FormErrorMessage from '@/components/ui/FormErrorMessage.vue';
import LoaderButtonSpinner from '@/components/ui/LoaderButtonSpinner.vue';
import { useLogoutAllDevices } from '@/composables/useAuth';

const { mutate: logoutAllDevices, isPending, isError } = useLogoutAllDevices();

// Подтверждение показывается вместо кнопки «Выйти везде»,
// как подтверждение удаления аккаунта
const isConfirmVisible = ref(false);

const showConfirm = (): void => {
    isConfirmVisible.value = true;
};

const hideConfirm = (): void => {
    isConfirmVisible.value = false;
};
</script>

<template>
    <section class="devices-section">
        <h3 class="section-title">Устройства</h3>

        <div v-if="!isConfirmVisible" class="account-current">
            <span class="account-value devices-hint">
                Выход из аккаунта на всех устройствах
            </span>

            <button type="button" class="change-btn" @click="showConfirm">
                Выйти везде
            </button>
        </div>

        <div v-else>
            <p class="confirm-text">
                Выйти на всех устройствах, включая это? Вход с «Запомнить меня»
                тоже перестанет действовать.
            </p>

            <FormErrorMessage
                class="form-message"
                :show="isError"
                message="Не удалось выйти. Попробуйте позже."
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
                    @click="logoutAllDevices()"
                >
                    <LoaderButtonSpinner v-if="isPending" :size="18" />

                    <span v-else>Выйти везде</span>
                </button>
            </div>
        </div>
    </section>
</template>

<style scoped lang="scss">
@use '../../../scss/ui/createButton.scss';
@use '../../../scss/ui/accountSection.scss';

.devices-section {
    margin-top: 20px;
    padding-top: 18px;
    border-top: 1px dashed rgba(139, 92, 246, 0.2);
}

// Пояснение в строке с кнопкой переносится, а не обрезается
.devices-hint {
    white-space: normal;
    font-size: 13px;
    color: var(--ink-soft, #6b5878);
}

.confirm-text {
    margin: 0 0 14px;
    font-size: 13px;
    font-weight: 600;
    line-height: 1.5;
    color: var(--ink, #241533);
}
</style>
