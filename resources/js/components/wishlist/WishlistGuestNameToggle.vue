<script setup lang="ts">
import { UserRound } from '@lucide/vue';

import ToggleSwitch from '../ui/ToggleSwitch.vue';

// Должен ли гость указать имя, отмечая дела по ссылке (guestNameRequired).
// Имя выводится под отмеченными делами
const model = defineModel<boolean>({ required: true });
</script>

<template>
    <ToggleSwitch
        v-model="model"
        title="Гость указывает имя"
        hint="Без имени отметить дело нельзя; под делом будет видно, кто его отметил"
        :icon="UserRound"
    />
</template>
