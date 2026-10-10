<script setup lang="ts">
import { Link } from '@lucide/vue';
import { computed } from 'vue';

import type { WishlistType } from '../../types/wishlist';
import ToggleSwitch from '../ui/ToggleSwitch.vue';

// Доступ к списку по ссылке (isShared). В списке дел отмечать дела гости могут,
// только если владелец включил ещё и WishlistGuestCheckToggle
const model = defineModel<boolean>({ required: true });

const props = defineProps<{
    type: WishlistType;
}>();

const hint = computed(() =>
    props.type === 'gift'
        ? 'Любой, у кого есть ссылка, увидит список и сможет выбрать подарок'
        : 'Любой, у кого есть ссылка, увидит список',
);
</script>

<template>
    <ToggleSwitch
        v-model="model"
        title="Доступ по ссылке"
        :hint="hint"
        :icon="Link"
    />
</template>
