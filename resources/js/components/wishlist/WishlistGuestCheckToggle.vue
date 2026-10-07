<script setup lang="ts">
import { ListChecks } from '@lucide/vue';

import ToggleSwitch from '../ui/ToggleSwitch.vue';

// Разрешение гостям отмечать дела по ссылке (guestsCanCheck). Гость только
// ставит отметку: снять её может лишь владелец
const model = defineModel<boolean>({ required: true });
</script>

<template>
    <ToggleSwitch
        v-model="model"
        title="Гости отмечают дела"
        hint="По ссылке можно отметить дело выполненным; снять отметку можете только вы"
        :icon="ListChecks"
    />
</template>
