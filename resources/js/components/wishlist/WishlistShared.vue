<script setup lang="ts">
import { Gift, Save } from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';

import { api } from '@/lib/api';
import type { Wishlist, WishlistItem } from '@/types/wishlist';

import LaoderPageSpinner from '../ui/LaoderPageSpinner.vue';

const route = useRoute();
const wishlist = ref<Wishlist | null>(null);
const isLoading = ref(true);
const error = ref<string | null>(null);
const wishlistId = String(route.params.id || '');
const selectedItems = ref<string[]>([]);
const isSaving = ref(false);

const getWishlist = async (): Promise<void> => {
    if (!wishlistId) {
        error.value = 'Не указан идентификатор списка';
        isLoading.value = false;
        return;
    }

    isLoading.value = true;
    error.value = null;

    try {
        const response = await api.get<{ data: Wishlist }>(
            `/api/v1/shared-wishlists/${wishlistId}`,
        );
        wishlist.value = response.data.data;
    } catch (fetchError) {
        console.error('Ошибка загрузки списка:', fetchError);
        error.value =
            'Не удалось загрузить список. Проверьте URL или попробуйте позже.';
        wishlist.value = null;
    } finally {
        isLoading.value = false;
        isSaving.value = false;
    }
};

const save = async (): Promise<void> => {
    if (selectedItems.value.length === 0 || isSaving.value) {
        return;
    }

    isSaving.value = true;

    try {
        const response = await api.patch<{ data: Wishlist }>(
            `/api/v1/shared-wishlists/${wishlistId}/items`,
            {
                item_ids: selectedItems.value,
            },
        );

        // Обновляем список актуальными данными с сервера
        wishlist.value = response.data.data;

        // Очищаем локальный список выбранных элементов
        selectedItems.value = [];
    } catch (error) {
        console.error('Ошибка сохранения:', error);
    } finally {
        isSaving.value = false;
    }
};

const hasChanges = computed(() => selectedItems.value.length > 0);

const toggleItem = (item: WishlistItem): void => {
    // Уже выбран кем-то другим — ничего не делаем
    if (item.isSelected) {
        return;
    }

    const index = selectedItems.value.indexOf(item.id);

    if (index === -1) {
        selectedItems.value.push(item.id);
    } else {
        selectedItems.value.splice(index, 1);
    }
};

onMounted(getWishlist);
</script>

<template>
    <div class="wishlist-view">
        <div v-if="isLoading || isSaving" class="loader">
            <LaoderPageSpinner />
        </div>

        <div v-else-if="error" class="error">
            <p>{{ error }}</p>
        </div>

        <div v-else-if="wishlist" class="content">
            <div class="card">
                <div class="card-header">
                    <div class="card-author">
                        <span class="card-author-avatar">
                            {{ wishlist.username?.charAt(0).toUpperCase() }}
                        </span>
                        <span>
                            <span class="card-author-name">
                                {{ wishlist.username }}
                            </span>
                        </span>
                    </div>
                    <span class="card-title">
                        {{ wishlist.title }}
                    </span>
                </div>

                <div
                    v-for="(item, itemIndex) in wishlist.items"
                    :key="itemIndex"
                    :class="[
                        'item',
                        {
                            disabled: item.isSelected,
                            'item--mine': selectedItems.includes(item.id),
                        },
                    ]"
                    :title="item.isSelected ? 'Уже выбрано' : undefined"
                >
                    <!-- Позицию уже выбрал другой гость: серый значок подарка вместо чекбокса -->
                    <span
                        v-if="item.isSelected"
                        class="reserved-icon"
                        role="img"
                        aria-label="Уже выбрано"
                    >
                        <Gift :size="11" />
                    </span>

                    <label v-else class="checkbox-wrapper">
                        <input
                            type="checkbox"
                            class="checkbox-input"
                            :checked="selectedItems.includes(item.id)"
                            @change="toggleItem(item)"
                        />

                        <span class="checkbox-custom"></span>
                    </label>

                    <span
                        :class="[
                            'item-label',
                            { 'reserved-text': item.isSelected },
                        ]"
                    >
                        {{ item.label }}
                    </span>
                </div>

                <div class="card-actions">
                    <button
                        class="card-actions-btn"
                        v-if="hasChanges"
                        @click="save"
                        :disabled="isSaving"
                    >
                        <Save :size="20" />
                    </button>
                </div>
            </div>
        </div>

        <div v-else class="empty-state">
            <p>Список не найден.</p>
        </div>
    </div>
</template>

<style scoped lang="scss">
@use '../../../scss/ui/checkboxCard.scss';

.wishlist-view {
    max-width: 800px;
    margin: 0 auto;
    padding: 24px;
}

.loader,
.error,
.empty-state {
    text-align: center;
    color: #6b7280;
    font-size: 16px;
}

.error p {
    color: #dc2626;
}

.card {
    position: relative;
    display: flex;
    justify-content: center;
    flex-direction: column;
    background: linear-gradient(145deg, #fff 60%, #fff7fd);
    border: 1px solid rgba(226, 195, 211, 0.5);
    border-radius: 18px;
    padding: 18px;
    min-width: 300px;
}

.card-actions {
    position: absolute;
    display: flex;
    gap: 4px;
    top: 6px;
    right: 10px;
    font-size: 10px;
    color: #b3b3b3;

    .card-actions-btn {
        display: grid;
        place-items: center;
        width: 30px;
        height: 30px;
        border-radius: 8px;
        transition: all 0.18s ease;

        &:hover:not(:disabled) {
            color: #fff;
            background: var(--brand-gradient);
            cursor: pointer;
        }
    }
}

.card-header {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: space-between;
    gap: 6px;
    margin-top: 8px;
    margin-bottom: 14px;
}

.card-author {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: #b08cbe;
}

.card-author-avatar {
    display: grid;
    place-items: center;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: var(--brand-gradient);
    font-size: 10px;
    font-weight: 700;
    color: #fff;
    box-shadow: var(--shadow-glow);
}

.card-author-name {
    font-weight: 600;
    color: #8b5cf6;
}

.card-title {
    font-size: 14px;
    font-weight: 600;
    color: #3b2146;
}

.item {
    position: relative;
    // Свой контекст наложения: фон выбранной строки (::before, z-index: -1)
    // выводится под содержимым строки, но поверх фона карточки
    isolation: isolate;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 7px 0;
    border-bottom: 1px solid rgba(226, 195, 211, 0.25);
    cursor: pointer;
    user-select: none;
}

.item:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.item:first-of-type {
    padding-top: 0;
}

.item-label {
    font-size: 13px;
    color: #4a3356;
    transition: color 0.15s;
    line-height: 1.35;
}

.item.disabled {
    cursor: default;
}

/* Позиция, выбранная этим гостем (ещё не сохранена): тот же розовый фон,
   что у забронированных позиций на /dashboard.
   Фон задан псевдоэлементом, а не самой строкой: он не меняет размер строки
   (строка не прыгает при выборе). Отступ 1px сверху; снизу фон доходит до края
   строки, ещё 1px даёт прозрачная нижняя граница. Между соседними выбранными
   строками остаётся зазор 2px, как на /dashboard */
.item.item--mine {
    border-bottom-color: transparent;

    &::before {
        content: '';
        position: absolute;
        inset: 1px -8px 0;
        z-index: -1;
        border-radius: 10px;
        background: rgba(236, 72, 153, 0.06);
    }
}

/* Позиция, выбранная другим гостем: приглушённый значок и текст */
.reserved-icon {
    display: grid;
    place-items: center;
    flex-shrink: 0;
    width: 19px;
    height: 19px;
    border-radius: 7px;
    background: linear-gradient(135deg, #cbd5e1, #94a3b8);
    color: #fff;
}

.reserved-text {
    color: #94a3b8;
}
</style>
