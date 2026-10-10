<script setup lang="ts">
import { X } from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref } from 'vue';

import { lockBodyScroll, unlockBodyScroll } from '../../lib/bodyScrollLock';
import type { Wishlist, WishlistItem } from '../../types/wishlist';
import WishlistCard from './WishlistCard.vue';

const props = defineProps<{
    wishlist: Wishlist;
}>();

const emit = defineEmits<{
    close: [];
    edit: [wishlist: Wishlist];
    duplicate: [wishlist: Wishlist];
    delete: [wishlist: Wishlist];
    archive: [wishlist: Wishlist];
    restore: [wishlist: Wishlist];
    toggleItem: [wishlist: Wishlist, item: WishlistItem];
    updateContent: [wishlist: Wishlist, content: string];
}>();

const overlayRef = ref<HTMLElement | null>(null);
const dialogRef = ref<HTMLElement | null>(null);

// У заметки нет названия
const dialogLabel = computed(() => props.wishlist.title || 'Заметка');

const closePreview = (): void => {
    emit('close');
};

// Esc закрывает только верхнее окно: поверх просмотра может быть открыто
// подтверждение удаления
const handleKeydown = (event: KeyboardEvent): void => {
    if (event.key !== 'Escape') return;

    const overlays = document.querySelectorAll('.modal-overlay');

    if (overlays[overlays.length - 1] === overlayRef.value) {
        closePreview();
    }
};

onMounted(() => {
    lockBodyScroll();
    window.addEventListener('keydown', handleKeydown);
    dialogRef.value?.focus();
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeydown);
    unlockBodyScroll();
});
</script>

<template>
    <div ref="overlayRef" class="modal-overlay" @click.self="closePreview">
        <div
            ref="dialogRef"
            class="preview"
            role="dialog"
            aria-modal="true"
            :aria-label="dialogLabel"
            tabindex="-1"
        >
            <WishlistCard
                :wishlist="wishlist"
                expanded
                @edit="emit('edit', $event)"
                @duplicate="emit('duplicate', $event)"
                @delete="emit('delete', $event)"
                @archive="emit('archive', $event)"
                @restore="emit('restore', $event)"
                @toggle-item="(list, item) => emit('toggleItem', list, item)"
                @update-content="
                    (list, content) => emit('updateContent', list, content)
                "
            />

            <!-- В ряду кнопок карточки, справа от них -->
            <button
                type="button"
                class="preview-close"
                aria-label="Закрыть"
                title="Закрыть"
                @click="closePreview"
            >
                <X :size="16" />
            </button>
        </div>
    </div>
</template>

<style scoped lang="scss">
@use '../../../scss/ui/wishlistModal.scss';

// Белая подложка: фон карточки полупрозрачный и на затемнении выглядел бы тусклым
.preview {
    position: relative;
    width: 100%;
    max-width: 560px;
    max-height: calc(100vh - 32px);
    margin: auto;
    overflow-y: auto;
    border-radius: var(--radius-lg);
    background: #fff;
    animation: modalPop 0.22s ease-out;
    @include wishlistModal.modal-scrollbar;

    &:focus {
        outline: none;
    }
}

.preview-close {
    position: absolute;
    top: 14px;
    right: 16px;
    display: grid;
    place-items: center;
    width: 28px;
    height: 28px;
    border-radius: 8px;
    color: #b3b3b3;
    cursor: pointer;
    transition: all 0.18s ease;

    &:hover {
        color: #fff;
        background: var(--brand-gradient);
    }
}
</style>
