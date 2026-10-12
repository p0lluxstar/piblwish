<script setup lang="ts">
import type { Component } from 'vue';
import { computed } from 'vue';

import FormErrorMessage from '@/components/ui/FormErrorMessage.vue';
import ToggleSwitch from '@/components/ui/ToggleSwitch.vue';
import {
    type UserToggleSetting,
    useUpdateUserToggle,
} from '@/composables/useAuth';
import { useAuthStore } from '@/stores/auth';

// Переключатель логической настройки аккаунта («Скоро у друзей», напоминания
// о сроке); сохраняется сразу, без отдельной кнопки, как выбор фона
const props = defineProps<{
    setting: UserToggleSetting;
    title: string;
    hint: string;
    icon: Component;
}>();

const auth = useAuthStore();
const { mutate: updateSetting, errorMessage } = useUpdateUserToggle(
    props.setting,
);

const model = computed({
    get: (): boolean => auth.user?.[props.setting] ?? true,
    set: (value: boolean): void => {
        updateSetting(value);
    },
});
</script>

<template>
    <div>
        <ToggleSwitch
            v-model="model"
            :title="title"
            :hint="hint"
            :icon="icon"
        />

        <FormErrorMessage
            class="error-message"
            :show="!!errorMessage"
            :message="errorMessage ?? undefined"
        />
    </div>
</template>

<style scoped lang="scss">
.error-message {
    margin: 10px 0 0;
    font-size: 13px;
}
</style>
