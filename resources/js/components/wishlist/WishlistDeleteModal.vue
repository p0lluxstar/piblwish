<script setup lang="ts">
import { onMounted, onUnmounted } from 'vue';

import type { Wishlist } from '../../types/wishlist';
import LoaderButtonSpinner from '../ui/LoaderButtonSpinner.vue';

const props = defineProps<{
    wishlist: Wishlist;
    isPending: boolean;
}>();

const emit = defineEmits<{
    close: [];
    confirm: [];
}>();

const closeModal = (): void => {
    emit('close');
};

const handleConfirm = (): void => {
    emit('confirm');
};

// Блокировка прокрутки при открытии модального окна
const disableBodyScroll = (): void => {
    document.body.classList.add('modal-open');
};

const enableBodyScroll = (): void => {
    document.body.classList.remove('modal-open');
};

onMounted(() => {
    disableBodyScroll();
});

onUnmounted(() => {
    enableBodyScroll();
});
</script>

<template>
    <div class="modal-overlay" @click.self="closeModal">
        <div class="modal">
            <div class="modal-header">
                <h2>Удаление списка</h2>
                <button class="close-btn" @click="closeModal">×</button>
            </div>

            <div class="modal-body">
                <p>
                    Вы действительно хотите удалить список «
                    <strong>{{ wishlist.title }}</strong>
                    »?
                </p>
                <p class="warning-text">Это действие необратимо.</p>
            </div>

            <div class="modal-actions">
                <button
                    type="button"
                    class="cancel-btn"
                    :disabled="props.isPending"
                    @click="closeModal"
                >
                    Отмена
                </button>
                <button
                    type="button"
                    class="delete-btn"
                    :disabled="props.isPending"
                    @click="handleConfirm"
                >
                    <LoaderButtonSpinner v-if="props.isPending" :size="18" />
                    <span v-else>Удалить</span>
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped lang="scss">
@use '../../../scss/ui/wishlistModal.scss';

.modal-body {
    margin-bottom: 24px;
    color: var(--ink, #241533);
    font-size: 14px;
    line-height: 1.5;

    p {
        margin: 0 0 8px 0;
    }

    .warning-text {
        font-size: 12px;
        font-weight: 600;
        color: #ec4899;
        margin-bottom: 0;
    }
}

.modal-actions {
    display: flex;
    gap: 12px;
}

.cancel-btn {
    display: flex;
    justify-content: center;
    align-items: center;
    background: rgba(139, 92, 246, 0.08);
    border: none;
    color: var(--ink, #241533);
    border-radius: 18px;
    font-size: 13px;
    font-weight: 600;
    font-family: inherit;
    flex: 1;
    padding: 12px;
    cursor: pointer;
    transition: all 0.2s ease;

    &:hover:not(:disabled) {
        background: rgba(139, 92, 246, 0.14);
    }

    &:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }
}

.delete-btn {
    display: flex;
    justify-content: center;
    align-items: center;
    background: linear-gradient(135deg, #fb7185, #ec4899);
    border: none;
    color: #fff;
    border-radius: 18px;
    font-size: 13px;
    font-weight: 700;
    font-family: inherit;
    flex: 1;
    padding: 12px;
    cursor: pointer;
    transition: all 0.2s ease;
    box-shadow: 0 14px 30px -12px rgba(236, 72, 153, 0.5);

    &:hover:not(:disabled) {
        transform: translateY(-2px);
        box-shadow: 0 18px 36px -12px rgba(236, 72, 153, 0.55);
    }

    &:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
    }
}
</style>
