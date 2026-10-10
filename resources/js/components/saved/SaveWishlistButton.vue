<script setup lang="ts">
import { BookmarkCheck, BookmarkPlus } from '@lucide/vue';
import { computed } from 'vue';

import LoaderButtonSpinner from '@/components/ui/LoaderButtonSpinner.vue';
import {
    useSavedWishlists,
    useToggleSavedWishlist,
} from '@/composables/useSavedWishlists';
import { useAuthStore } from '@/stores/auth';

// Кнопка на общей странице: добавить список в раздел «Чужие списки» или убрать
// его оттуда. Видна только вошедшему пользователю и не на его собственном списке
const props = defineProps<{
    wishlistId: string;
    // Владелец списка; имена пользователей уникальны, поэтому по имени
    // общая страница узнаёт собственный список вошедшего пользователя
    ownerUsername: string | null;
}>();

const auth = useAuthStore();

const isVisible = computed(
    () =>
        Boolean(auth.user) &&
        Boolean(props.ownerUsername) &&
        props.ownerUsername !== auth.user?.username,
);

const { data, isLoading } = useSavedWishlists(isVisible);

const isSaved = computed(() =>
    (data.value ?? []).some((saved) => saved.id === props.wishlistId),
);

const {
    mutate: toggleSaved,
    isPending,
    errorMessage,
} = useToggleSavedWishlist();

const toggle = (): void => {
    toggleSaved({ id: props.wishlistId, save: !isSaved.value });
};
</script>

<template>
    <div v-if="isVisible && !isLoading" class="save-wishlist">
        <button
            :class="['save-btn', { 'save-btn--saved': isSaved }]"
            type="button"
            :aria-pressed="isSaved"
            :disabled="isPending"
            :title="isSaved ? 'Убрать из чужих списков' : undefined"
            @click="toggle"
        >
            <!-- Содержимое задаёт ширину кнопки, во время запроса поверх него спиннер -->
            <span :class="['save-btn-content', { 'is-hidden': isPending }]">
                <BookmarkCheck v-if="isSaved" :size="14" />
                <BookmarkPlus v-else :size="14" />
                {{ isSaved ? 'В ваших чужих списках' : 'Добавить к себе' }}
            </span>

            <LoaderButtonSpinner
                v-if="isPending"
                class="save-btn-spinner"
                :size="14"
            />
        </button>

        <p v-if="errorMessage" class="save-error" role="alert">
            {{ errorMessage }}
        </p>
    </div>
</template>

<style scoped lang="scss">
.save-wishlist {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    margin-top: 4px;
}

.save-btn {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 7px 16px;
    border: none;
    border-radius: 999px;
    background: var(--brand-gradient);
    color: #fff;
    font-size: 13px;
    font-weight: 700;
    font-family: inherit;
    cursor: pointer;
    box-shadow: var(--shadow-glow);
    transition: all 0.2s ease;

    &:hover:not(:disabled) {
        transform: translateY(-1px);
        box-shadow: var(--shadow-glow-lg);
    }

    &:disabled {
        cursor: wait;
    }
}

// Список уже добавлен: спокойная кнопка, по нажатию закладка убирается
.save-btn--saved {
    border: 1.5px solid var(--surface-border);
    background: #fff;
    color: var(--brand-violet);
    box-shadow: none;

    &:hover:not(:disabled) {
        box-shadow: var(--shadow-glow);
    }
}

.save-btn-content {
    display: inline-flex;
    align-items: center;
    gap: 6px;

    &.is-hidden {
        visibility: hidden;
    }
}

.save-btn-spinner {
    position: absolute;
}

.save-error {
    margin: 0;
    font-size: 12px;
    font-weight: 600;
    color: #dc2626;
}
</style>
