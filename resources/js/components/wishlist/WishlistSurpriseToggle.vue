<script setup lang="ts">
import { Eye } from '@lucide/vue';
import { computed } from 'vue';

import ToggleSwitch from '../ui/ToggleSwitch.vue';

// Модель — флаг режима сюрприза (hideSelections), а переключатель показывает
// обратное значение «Показывать выбранные подарки»: выключенное состояние
// соответствует сюрпризу, раскрыть выбор гостей владелец решает сам
const model = defineModel<boolean>({ required: true });

const showSelections = computed({
    get: (): boolean => !model.value,
    set: (value: boolean): void => {
        model.value = !value;
    },
});
</script>

<template>
    <ToggleSwitch
        v-model="showSelections"
        title="Показывать выбранные подарки"
        hint="Вы увидите, какие подарки выбрали гости"
        :icon="Eye"
    />
</template>
