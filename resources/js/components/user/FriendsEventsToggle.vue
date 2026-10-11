<script setup lang="ts">
import { PartyPopper } from '@lucide/vue';
import { computed } from 'vue';

import FormErrorMessage from '@/components/ui/FormErrorMessage.vue';
import ToggleSwitch from '@/components/ui/ToggleSwitch.vue';
import { useUpdateShowFriendsEvents } from '@/composables/useAuth';
import { useAuthStore } from '@/stores/auth';

// Переключатель строки «Скоро у друзей» в дашборде; сохраняется сразу,
// без отдельной кнопки, как выбор фона
const auth = useAuthStore();
const { mutate: updateShowFriendsEvents, errorMessage } =
    useUpdateShowFriendsEvents();

const model = computed({
    get: (): boolean => auth.user?.showFriendsEvents ?? true,
    set: (value: boolean): void => {
        updateShowFriendsEvents(value);
    },
});
</script>

<template>
    <div>
        <ToggleSwitch
            v-model="model"
            title="Скоро у друзей"
            hint="Ближайшие даты чужих списков"
            :icon="PartyPopper"
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
