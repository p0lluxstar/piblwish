<script setup lang="ts">
import { Link } from '@lucide/vue';

import ToggleSwitch from '../ui/ToggleSwitch.vue';

// Доступ к списку дел по ссылке (isShared). Отмечать дела гости могут,
// только если владелец включил ещё и WishlistGuestCheckToggle
const model = defineModel<boolean>({ required: true });
</script>

<template>
    <ToggleSwitch
        v-model="model"
        title="Доступ по ссылке"
        hint="Любой, у кого есть ссылка, увидит список"
        :icon="Link"
    />
</template>
