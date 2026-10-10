<script setup lang="ts">
import { Gift, Hourglass, ListChecks, PartyPopper, X } from '@lucide/vue';
import { computed, ref } from 'vue';

import UserAvatar from '@/components/ui/UserAvatar.vue';
import { dueDateTitle, formatDueDate, getDueDateStatus } from '@/lib/dueDate';
import { getSharedWishlistPath } from '@/lib/sharedLink';
import type { SavedWishlist } from '@/types/savedWishlist';

// Карточка чужого списка в разделе «Чужие списки»: сводка и переход на общую страницу.
// Позиции на карточке не выводятся, их видно на общей странице
const props = defineProps<{
    wishlist: SavedWishlist;
    // Запрос на удаление этой закладки выполняется
    isRemoving: boolean;
}>();

const emit = defineEmits<{
    remove: [wishlist: SavedWishlist];
}>();

const isTodo = computed(() => props.wishlist.type === 'todo');

const sharedPath = computed(() =>
    getSharedWishlistPath(props.wishlist.id, props.wishlist.type ?? 'gift'),
);

const ownerName = computed(() => props.wishlist.username ?? 'Пользователь');

// «Выбрано 2 из 5» у списка желаний, «Выполнено 2 из 5» у списка дел
const progressLabel = computed(() => {
    const { itemsCount, selectedCount } = props.wishlist;

    if (!itemsCount) return 'Список пуст';

    return `${isTodo.value ? 'Выполнено' : 'Выбрано'} ${selectedCount ?? 0} из ${itemsCount}`;
});

const isCompleted = computed(() => {
    const { itemsCount, selectedCount } = props.wishlist;

    return Boolean(itemsCount) && selectedCount === itemsCount;
});

const dueStatus = computed(() => {
    const { dueDate, type } = props.wishlist;

    if (!dueDate || !type) return null;

    return getDueDateStatus(type, dueDate, isCompleted.value);
});

const dueDateLabel = computed(() => {
    const { dueDate, type } = props.wishlist;

    return dueDate && type
        ? `${dueDateTitle(type)}: ${formatDueDate(dueDate)}`
        : '';
});

// Подтверждение показывается на карточке: без закладки список
// можно будет открыть снова только по ссылке
const isConfirmVisible = ref(false);

const confirmRemove = (): void => {
    emit('remove', props.wishlist);
};
</script>

<template>
    <article
        :class="[
            'card',
            wishlist.color ? `wishlist-color--${wishlist.color}` : '',
            { 'card--unavailable': !wishlist.available },
        ]"
    >
        <div class="card-top">
            <span
                v-if="wishlist.available"
                :class="[
                    'card-type',
                    isTodo ? 'card-type--todo' : 'card-type--gift',
                ]"
            >
                <ListChecks v-if="isTodo" :size="12" />
                <Gift v-else :size="12" />
                {{ isTodo ? 'Дела' : 'Желания' }}
            </span>

            <button
                class="remove-btn"
                type="button"
                aria-label="Убрать из чужих списков"
                title="Убрать из чужих списков"
                :disabled="isRemoving"
                @click="isConfirmVisible = true"
            >
                <X :size="14" />
            </button>
        </div>

        <div class="card-owner">
            <UserAvatar
                class="card-owner-avatar"
                :username="ownerName"
                :avatar-url="wishlist.avatarUrl"
            />
            <span class="card-owner-name">{{ ownerName }}</span>
        </div>

        <template v-if="wishlist.available">
            <router-link :to="sharedPath" class="card-title">
                {{ wishlist.title }}
            </router-link>

            <p class="card-progress">{{ progressLabel }}</p>
        </template>

        <p v-else class="card-unavailable-text">
            Владелец закрыл доступ к этому списку
        </p>

        <div v-if="isConfirmVisible" class="card-confirm" role="group">
            <p class="card-confirm-text">
                Убрать список? Открыть его снова можно будет только по ссылке.
            </p>

            <div class="card-confirm-actions">
                <button
                    class="card-confirm-btn"
                    type="button"
                    :disabled="isRemoving"
                    @click="isConfirmVisible = false"
                >
                    Отмена
                </button>
                <button
                    class="card-confirm-btn card-confirm-btn--danger"
                    type="button"
                    :disabled="isRemoving"
                    @click="confirmRemove"
                >
                    Убрать
                </button>
            </div>
        </div>

        <div v-else class="card-footer">
            <time
                v-if="dueStatus"
                :class="['card-due', `card-due--${dueStatus.tone}`]"
                :datetime="wishlist.dueDate ?? undefined"
                :title="dueDateLabel"
            >
                <Hourglass v-if="isTodo" :size="12" aria-hidden="true" />
                <PartyPopper v-else :size="12" aria-hidden="true" />
                <span class="sr-only">{{ dueDateLabel }}.</span>
                {{ dueStatus.text }}
            </time>

            <router-link
                v-if="wishlist.available"
                :to="sharedPath"
                class="open-link"
            >
                Открыть
            </router-link>
        </div>
    </article>
</template>

<style scoped lang="scss">
@use '../../../scss/ui/wishlistColors.scss';
@use '../../../scss/ui/dueDateBadge.scss';

// Как карточка владельца (WishlistCard), но без подъёма при наведении:
// карточка открывается ссылками в названии и в кнопке «Открыть»
.card {
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 14px 16px 16px;
    border: 1px solid var(--surface-border);
    border-radius: var(--radius-lg);
    background: var(--wishlist-bg, var(--surface));
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    box-shadow: 0 8px 24px -14px
        color-mix(in srgb, var(--wishlist-glow, #64748b) 25%, transparent);
}

.card--unavailable {
    opacity: 0.75;
}

.card-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    min-height: 24px;
}

// Как метки типа в WishlistCard
.card-type {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    height: 22px;
    padding: 0 8px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 600;
    white-space: nowrap;
}

.card-type--gift {
    background: rgba(139, 92, 246, 0.12);
    color: var(--brand-violet);
}

.card-type--todo {
    background: rgba(16, 185, 129, 0.14);
    color: #059669;
}

.remove-btn {
    display: grid;
    place-items: center;
    width: 24px;
    height: 24px;
    margin-left: auto;
    border: none;
    border-radius: 8px;
    background: none;
    color: #b3b3b3;
    cursor: pointer;
    transition: all 0.18s ease;

    &:hover:not(:disabled) {
        color: #fff;
        background: var(--brand-gradient);
    }

    &:disabled {
        cursor: not-allowed;
    }
}

.card-owner {
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
}

.card-owner-avatar {
    width: 26px;
    height: 26px;
    flex-shrink: 0;
    background: var(--brand-gradient);
    color: #fff;
    font-size: 12px;
    font-weight: 700;
}

.card-owner-name {
    overflow: hidden;
    font-size: 13px;
    font-weight: 600;
    color: var(--ink-soft);
    text-overflow: ellipsis;
    white-space: nowrap;
}

.card-title {
    font-size: 15px;
    font-weight: 700;
    color: var(--ink);
    overflow-wrap: anywhere;
    text-decoration: none;

    &:hover {
        color: var(--brand-violet);
    }
}

.card-progress,
.card-unavailable-text {
    margin: 0;
    font-size: 12px;
    color: var(--ink-soft);
}

.card-footer {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: auto;
    padding-top: 4px;
}

// Плашка срока слева, кнопка «Открыть» справа
.card-footer .card-due {
    margin-left: 0;
}

.open-link {
    margin-left: auto;
    padding: 6px 14px;
    border-radius: 999px;
    background: var(--brand-gradient);
    color: #fff;
    font-size: 12px;
    font-weight: 700;
    text-decoration: none;
    box-shadow: var(--shadow-glow);
    transition: transform 0.2s ease;

    &:hover {
        transform: translateY(-1px);
    }
}

.card-confirm {
    margin-top: auto;
}

.card-confirm-text {
    margin: 0 0 10px;
    font-size: 12px;
    font-weight: 600;
    line-height: 1.4;
    color: var(--ink);
}

.card-confirm-actions {
    display: flex;
    gap: 8px;
}

.card-confirm-btn {
    flex: 1;
    padding: 7px;
    border: 1.5px solid var(--surface-border);
    border-radius: 14px;
    background: #fff;
    color: var(--brand-violet);
    font-size: 12px;
    font-weight: 700;
    font-family: inherit;
    cursor: pointer;

    &:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }
}

.card-confirm-btn--danger {
    border-color: transparent;
    background: linear-gradient(135deg, #fb7185, #ec4899);
    color: #fff;
}
</style>
