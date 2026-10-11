<script setup lang="ts">
import { computed } from 'vue';

import UserAvatar from '@/components/ui/UserAvatar.vue';
import { useSavedWishlists } from '@/composables/useSavedWishlists';
import {
    daysUntil,
    type DueDateStatus,
    dueDateTitle,
    formatDueDate,
    getDueDateStatus,
} from '@/lib/dueDate';
import { getSharedWishlistPath } from '@/lib/sharedLink';
import type { SavedWishlist } from '@/types/savedWishlist';

// Строка «Скоро у друзей» над карточками в дашборде: чужие списки с ближайшей
// датой, чтобы о событиях друзей не приходилось помнить и заходить в «Чужие списки».
// Без подходящих списков строка не выводится
const DAYS_AHEAD = 30;
const MAX_ITEMS = 3;
// Длина названия на плашке; полное название — во всплывающей подсказке
const MAX_TITLE_LENGTH = 10;

// Array.from считает символы, а не UTF-16-единицы: эмодзи не разрезается пополам
const shortenTitle = (title: string | null): string => {
    const chars = Array.from(title ?? '');

    return chars.length > MAX_TITLE_LENGTH
        ? `${chars.slice(0, MAX_TITLE_LENGTH).join('')}…`
        : chars.join('');
};

interface UpcomingItem {
    wishlist: SavedWishlist;
    days: number;
    status: DueDateStatus;
    path: string;
    shortTitle: string;
    dueDateLabel: string;
}

const { data } = useSavedWishlists();

// Только доступные списки с датой от сегодняшней до DAYS_AHEAD дней вперёд.
// Прошедшие даты не показываются, а выполненный список дел отсеивает
// getDueDateStatus: для него счётчика нет
const items = computed((): UpcomingItem[] =>
    (data.value ?? [])
        .flatMap((wishlist): UpcomingItem[] => {
            const { available, dueDate, type, itemsCount, selectedCount } =
                wishlist;

            if (!available || !dueDate || !type) return [];

            const days = daysUntil(dueDate);

            if (days < 0 || days > DAYS_AHEAD) return [];

            const isCompleted =
                Boolean(itemsCount) && selectedCount === itemsCount;
            const status = getDueDateStatus(type, dueDate, isCompleted);

            if (!status) return [];

            return [
                {
                    wishlist,
                    days,
                    status,
                    path: getSharedWishlistPath(wishlist.id, type),
                    shortTitle: shortenTitle(wishlist.title),
                    dueDateLabel: `${dueDateTitle(type)}: ${formatDueDate(dueDate)}`,
                },
            ];
        })
        .sort((a, b) => a.days - b.days)
        .slice(0, MAX_ITEMS),
);
</script>

<template>
    <!-- Одна строка ближайших списков; если они не помещаются по ширине,
         ряд прокручивается вбок -->
    <section
        v-if="items.length > 0"
        class="upcoming"
        aria-label="Скоро у друзей"
    >
        <ul class="upcoming-list">
            <li v-for="item in items" :key="item.wishlist.id">
                <router-link
                    :to="item.path"
                    :class="[
                        'upcoming-item',
                        item.wishlist.color
                            ? `wishlist-color--${item.wishlist.color}`
                            : '',
                    ]"
                    :title="`${item.wishlist.username ?? 'Пользователь'}: ${item.wishlist.title}. ${item.dueDateLabel}`"
                >
                    <UserAvatar
                        class="upcoming-avatar"
                        :username="item.wishlist.username ?? 'Пользователь'"
                        :avatar-url="item.wishlist.avatarUrl"
                    />

                    <!-- Диктор читает полное название, а не укороченное -->
                    <span class="upcoming-title" aria-hidden="true">
                        {{ item.shortTitle }}
                    </span>
                    <span class="sr-only">{{ item.wishlist.title }}</span>

                    <time
                        :class="['card-due', `card-due--${item.status.tone}`]"
                        :datetime="item.wishlist.dueDate ?? undefined"
                    >
                        <span class="sr-only">{{ item.dueDateLabel }}.</span>
                        {{ item.status.text }}
                    </time>
                </router-link>
            </li>
        </ul>
    </section>
</template>

<style scoped lang="scss">
@use '../../../scss/ui/wishlistColors.scss';
@use '../../../scss/ui/dueDateBadge.scss';

.upcoming {
    margin-bottom: 20px;
    min-width: 0;
}

.upcoming-list {
    display: flex;
    gap: 8px;
    min-width: 0;
    margin: 0;
    padding: 2px 0;
    list-style: none;
    overflow-x: auto;
    scrollbar-width: none;

    &::-webkit-scrollbar {
        display: none;
    }

    li {
        flex-shrink: 0;
    }
}

.upcoming-item {
    display: flex;
    align-items: center;
    gap: 6px;
    height: 30px;
    padding: 0 4px 0 3px;
    border: 1px solid var(--surface-border);
    border-radius: 999px;
    background: var(--wishlist-bg, var(--surface));
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    color: var(--ink);
    text-decoration: none;
    transition: border-color 0.2s ease;

    &:hover {
        border-color: var(--brand-violet);
    }
}

.upcoming-avatar {
    width: 22px;
    height: 22px;
    flex-shrink: 0;
    background: var(--brand-gradient);
    color: #fff;
    font-size: 11px;
    font-weight: 700;
}

.upcoming-title {
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
}

// Плашка срока из dueDateBadge.scss, чуть ниже, чтобы поместиться в строку
.upcoming-item .card-due {
    padding: 2px 7px;
}
</style>
