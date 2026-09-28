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

    const appUrl = import.meta.env.VITE_API_URL || window.location.origin;

    const fullUrl = `${appUrl}/shared-wishlists/${id}`;

    try {
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(fullUrl);
            console.log('Link copied:', fullUrl);
            return;
        }

        throw new Error('Clipboard API is unavailable in this context');
    } catch (err) {
        console.warn('Clipboard API failed, using fallback:', err);

        const textarea = document.createElement('textarea');
        textarea.value = fullUrl;
        textarea.style.position = 'fixed';
        textarea.style.left = '-9999px';
        textarea.style.top = '0';
        textarea.setAttribute('aria-hidden', 'true');

        document.body.appendChild(textarea);
        textarea.focus();
        textarea.select();

        try {
            const success = document.execCommand('copy');

            if (success) {
                console.log('Link copied via fallback:', fullUrl);
            } else {
                console.error('Fallback copy failed');
            }
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
    background: var(--surface);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border: 1px solid var(--surface-border);
    border-radius: var(--radius-lg);
    padding: 20px;
    box-shadow: 0 8px 24px -14px rgba(139, 92, 246, 0.25);
    transition:
        transform 0.25s ease,
        box-shadow 0.25s ease;
}

.card:hover {
    transform: translateY(-6px) scale(1.015);
    box-shadow: var(--shadow-glow-lg);
}

.card-actions {
    position: absolute;
    display: flex;
    gap: 6px;
    top: 10px;
    right: 12px;
    font-size: 10px;
    color: #b3b3b3;

    .card-actions-btn {
        display: grid;
        place-items: center;
        width: 24px;
        height: 24px;
        border-radius: 8px;
        transition: all 0.18s ease;

        &:hover {
            color: #fff;
            background: var(--brand-gradient);
            cursor: pointer;
        }
    }
}

.card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 10px;
    margin-bottom: 16px;
}

.card-title {
    font-size: 15px;
    font-weight: 700;
    color: var(--ink);
}

.item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 7px 0;
    border-bottom: 1px solid rgba(139, 92, 246, 0.1);
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
    color: var(--ink);
    transition: color 0.15s;
    line-height: 1.35;
}

.checked-text {
    color: #94a3b8;
    text-decoration: line-through;
}

.card-progress-container {
    margin-top: 10px;
}

.card-progress {
    width: 100%;
    height: 5px;
    background: rgba(139, 92, 246, 0.1);
    border-radius: 999px;
    overflow: hidden;
}

.card-progress-fill {
    height: 100%;
    background: var(--brand-gradient);
    border-radius: 999px;
    transition: width 0.3s ease;
}

.progress-percent {
    display: inline-block;
    width: 100%;
    font-size: 11px;
    font-weight: 700;
    color: var(--brand-violet);
    min-width: 45px;
    text-align: right;
}
</style>
