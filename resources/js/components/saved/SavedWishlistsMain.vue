<script setup lang="ts">
import { RefreshCcw } from '@lucide/vue';
import { computed, ref } from 'vue';

import LaoderPageSpinner from '@/components/ui/LaoderPageSpinner.vue';
import {
    useSavedWishlists,
    useToggleSavedWishlist,
} from '@/composables/useSavedWishlists';
import type { SavedWishlist } from '@/types/savedWishlist';

import SavedWishlistCard from './SavedWishlistCard.vue';

// Раздел «Чужие списки»: списки других пользователей, добавленные к себе
// кнопкой на общей странице
const { data, isLoading, isFetching, error, refetch } = useSavedWishlists();

const wishlists = computed(() => data.value ?? []);

const {
    mutate: toggleSaved,
    errorMessage: removeErrorMessage,
    reset: resetRemove,
} = useToggleSavedWishlist();

// Закладка, которая сейчас удаляется: её карточка блокирует кнопки
const removingId = ref<string | null>(null);

const removeWishlist = (wishlist: SavedWishlist): void => {
    resetRemove();
    removingId.value = wishlist.id;

    toggleSaved(
        { id: wishlist.id, save: false },
        {
            onSettled: () => {
                removingId.value = null;
            },
        },
    );
};

const refresh = (): void => {
    resetRemove();
    refetch();
};
</script>

<template>
    <div class="top">
        <div class="heading">Чужие списки</div>

        <button
            v-if="wishlists.length > 0"
            class="update-btn"
            aria-label="Обновить"
            :disabled="isFetching"
            @click="refresh"
        >
            <RefreshCcw :size="12" />
            <span class="btn-text">Обновить</span>
        </button>
    </div>

    <p v-if="removeErrorMessage" class="remove-error" role="alert">
        {{ removeErrorMessage }}
    </p>

    <div v-if="isLoading" class="state">
        <LaoderPageSpinner />
    </div>

    <div v-else-if="error" class="state">
        <p>Ошибка при загрузке</p>
    </div>

    <div v-else-if="wishlists.length === 0" class="state state--empty">
        <p class="empty-title">Здесь пока пусто</p>
        <p class="empty-text">
            Откройте список друга по ссылке и нажмите «Добавить к себе» — он
            появится в этом разделе, и ссылку больше не придётся искать.
        </p>
    </div>

    <div v-else class="grid">
        <SavedWishlistCard
            v-for="wishlist in wishlists"
            :key="wishlist.id"
            :wishlist="wishlist"
            :is-removing="removingId === wishlist.id"
            @remove="removeWishlist"
        />
    </div>
</template>

<style scoped lang="scss">
// Шапка раздела и сетка — как у «Моих карточек» (WishlistMain)
.top {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    margin-bottom: 20px;
}

.heading {
    align-self: flex-start;
    font-size: 26px;
    font-weight: 800;
    line-height: 1;
    color: var(--app-ink, var(--ink));
    letter-spacing: -0.03em;
}

.update-btn {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 9px 18px;
    border: 1.5px solid var(--surface-border);
    border-radius: 20px;
    background: #fff;
    color: var(--brand-violet);
    font-size: 13px;
    font-weight: 600;
    font-family: inherit;
    line-height: 1.5;
    white-space: nowrap;
    cursor: pointer;
    transition: all 0.2s ease;

    &:hover:not(:disabled) {
        transform: translateY(-2px) scale(1.02);
        box-shadow: var(--shadow-glow);
    }

    &:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }
}

// Узкий экран: кнопка круглая, только с иконкой, как в WishlistMain
@media (max-width: 599px) {
    .update-btn {
        justify-content: center;
        width: 38px;
        height: 38px;
        padding: 0;

        svg {
            width: 16px;
            height: 16px;
        }

        .btn-text {
            display: none;
        }
    }
}

.remove-error {
    margin: 0 0 16px;
    font-size: 13px;
    font-weight: 600;
    color: #dc2626;
}

.state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 300px;
    text-align: center;
    color: var(--app-ink-soft, #6b7280);
}

.empty-title {
    margin: 0 0 8px;
    font-size: 18px;
    font-weight: 700;
    color: var(--app-ink, var(--ink));
}

.empty-text {
    max-width: 420px;
    margin: 0;
    font-size: 14px;
    line-height: 1.5;
}

.grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    gap: 20px;
}
</style>
