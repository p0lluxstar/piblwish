<script setup lang="ts">
import { CloudOff, Link2Off } from '@lucide/vue';

import StatusCard from '../ui/StatusCard.vue';

// Заглушка общей страницы, когда список не открылся: на 404 сервер одинаково
// отвечает для удалённого, закрытого и несуществующего списка, поэтому причины
// перечисляются все сразу; при другой ошибке предлагается повторить загрузку
const props = defineProps<{
    notFound: boolean;
}>();

const emit = defineEmits<{
    retry: [];
}>();

const NOT_FOUND_REASONS = [
    'владелец закрыл доступ или сменил ссылку;',
    'список удалён или перенесён в архив;',
    'в адресе ошибка — проверьте, что ссылка скопирована целиком.',
];
</script>

<template>
    <StatusCard
        v-if="props.notFound"
        :icon="Link2Off"
        title="Список не найден"
        text="Возможно, по этой ссылке:"
        :reasons="NOT_FOUND_REASONS"
        hint="Если список нужен, попросите владельца прислать новую ссылку."
    />

    <StatusCard
        v-else
        :icon="CloudOff"
        title="Не удалось загрузить список"
        text="Проверьте подключение к интернету и попробуйте ещё раз."
        retry
        @retry="emit('retry')"
    />
</template>
