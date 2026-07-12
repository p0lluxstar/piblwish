<script setup lang="ts">
import { ExternalLink, FileEdit, Trash2 } from '@lucide/vue';

import type { Wishlist } from '../../types/wishlist';

const props = defineProps<{
    wishlist: Wishlist;
}>();

const emit = defineEmits<{
    edit: [wishlist: Wishlist];
}>();

const edit = (): void => {
    emit('edit', props.wishlist);
};

const copyLink = (): void => {};
const deleteCard = (): void => {};
</script>

<template>
    <div class="card">
        <div class="card-actions">
            <button class="card-actions-btn" @click="edit">
                <FileEdit :size="14" />
            </button>
            <button class="card-actions-btn" @click="copyLink">
                <ExternalLink :size="14" />
            </button>
            <button class="card-actions-btn" @click="deleteCard">
                <Trash2 :size="14" />
            </button>
        </div>
        <div class="card-header">
            <span class="card-title">
                {{ wishlist.title }}
            </span>
        </div>

        <div
            v-for="(item, itemIndex) in wishlist.items"
            :key="itemIndex"
            class="item"
        >
            <label class="checkbox-wrapper">
                <input
                    type="checkbox"
                    v-model="item.isSelected"
                    class="checkbox-input"
                />

                <span class="checkbox-custom"></span>
            </label>

            <span
                :class="[
                    'item-label',
                    {
                        'checked-text': item.isSelected,
                    },
                ]"
            >
                {{ item.label }}
            </span>
        </div>

        <div class="card-progress"></div>
    </div>
</template>

<style scoped lang="scss">
@use '../../../scss/ui/checkboxCard.scss';

.card {
    position: relative;
    background: linear-gradient(145deg, #fff 60%, #fff7fd);
    border: 1px solid rgba(226, 195, 211, 0.5);
    border-radius: 18px;
    padding: 18px;
}

.card-actions {
    position: absolute;
    display: flex;
    gap: 4px;
    top: 4px;
    right: 10px;
    font-size: 10px;
    color: #b3b3b3;

    .card-actions-btn {
        &:hover {
            color: #ff8fab;
            cursor: pointer;
        }
    }
}

.card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 14px;
}

.card-title {
    font-size: 14px;
    font-weight: 600;
    color: #3b2146;
}

.item {
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

.checked-text {
    color: #c5a4d8;
    text-decoration: line-through;
}

.card-progress {
    margin-top: 14px;
    height: 3px;
    background: rgba(196, 181, 253, 0.2);
    border-radius: 3px;
    overflow: hidden;
}
</style>
