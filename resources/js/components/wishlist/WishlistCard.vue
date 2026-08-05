<script setup lang="ts">
import { ExternalLink, FileEdit, Trash2 } from '@lucide/vue';
import { computed } from 'vue';

import type { Wishlist } from '../../types/wishlist';

const props = defineProps<{
    wishlist: Wishlist;
}>();

const emit = defineEmits<{
    edit: [wishlist: Wishlist];
    delete: [wishlist: Wishlist];
}>();

const editCard = (): void => {
    emit('edit', props.wishlist);
};

const deleteCard = (): void => {
    emit('delete', props.wishlist);
};

const copyLink = async (): Promise<void> => {
    const id = String(props.wishlist.id ?? '');
    if (!id) {
        console.warn('Wishlist id is empty, nothing to copy');
        return;
    }

    try {
        await navigator.clipboard.writeText(
            `http://localhost:8000/shared-wishlists/${id}`,
        );
        console.log('Wishlist id copied to clipboard:', id);
    } catch (err) {
        // fallback for older browsers / non-secure contexts
        const textarea = document.createElement('textarea');
        textarea.value = id;
        textarea.style.position = 'fixed';
        textarea.style.left = '-9999px';
        textarea.setAttribute('aria-hidden', 'true');
        document.body.appendChild(textarea);
        textarea.select();

        try {
            document.execCommand('copy');
            console.log('Wishlist id copied via execCommand:', id);
        } catch (err2) {
            console.error('Copy to clipboard failed:', err2);
        } finally {
            document.body.removeChild(textarea);
        }
    }
};

const progress = computed(() => {
    const items = props.wishlist.items;

    if (!items.length) return 0;

    const selected = items.filter((item) => item.isSelected).length;

    return (selected / items.length) * 100;
});
</script>

<template>
    <div class="card">
        <div class="card-actions">
            <button class="card-actions-btn" @click="editCard">
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
            <label class="checkbox-wrapper-disabled">
                <input
                    type="checkbox"
                    v-model="item.isSelected"
                    disabled
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

        <div class="card-progress-container">
            <span class="progress-percent">{{ progress }}%</span>
            <div class="card-progress">
                <div
                    class="card-progress-fill"
                    :style="{ width: `${progress}%` }"
                ></div>
            </div>
        </div>
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
    top: 6px;
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
    margin-top: 8px;
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
    // color: #c5a4d8;
    color: #94a3b8;
    text-decoration: line-through;
}

.card-progress-container {
    margin-top: 0px;
}

.card-progress {
    width: 100%;
    height: 3px;
    background: #e5e7eb;
    border-radius: 999px;
    overflow: hidden;
}

.card-progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #ff8fab, #c4b5fd);
    border-radius: 3px;
    transition: width 0.3s ease;
}

.progress-percent {
    display: inline-block;
    width: 100%;
    font-size: 10px;
    font-weight: 600;
    color: #898989;
    min-width: 45px;
    text-align: right;
    font-style: italic;
}
</style>
