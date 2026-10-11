<script setup lang="ts">
import { computed, ref, watch } from 'vue';

// Круглый аватар пользователя: фотография, если она загружена, иначе первая буква имени.
// Размер, фон, рамку и шрифт задаёт родитель своим классом; фон родителя виден,
// пока фотография загружается, и под буквой, если фотографии нет
const props = defineProps<{
    username: string;
    avatarUrl?: string | null;
}>();

const initial = computed(() => props.username.charAt(0).toUpperCase());

// Фотография не загрузилась (файл удалён, сеть) — показываем букву.
// Новый адрес фотографии сбрасывает ошибку
const hasImageError = ref(false);

watch(
    () => props.avatarUrl,
    () => {
        hasImageError.value = false;
    },
);

const showImage = computed(
    () => Boolean(props.avatarUrl) && !hasImageError.value,
);
</script>

<template>
    <!-- Имя пользователя всегда выведено рядом, поэтому аватар скрыт от экранного диктора -->
    <span class="user-avatar" aria-hidden="true">
        <img
            v-if="showImage"
            class="user-avatar-image"
            :src="avatarUrl ?? undefined"
            alt=""
            draggable="false"
            @error="hasImageError = true"
        />
        <template v-else>{{ initial }}</template>
    </span>
</template>

<style scoped>
.user-avatar {
    display: grid;
    flex-shrink: 0;
    place-items: center;
    overflow: hidden;
    border-radius: 50%;
    line-height: 1;
    user-select: none;
}

.user-avatar-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
</style>
