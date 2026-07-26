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
    color: #4a3356;
    font-size: 14px;
    line-height: 1.5;

    p {
        margin: 0 0 8px 0;
    }

    .warning-text {
        font-size: 12px;
        color: #ff8fab;
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
    background: #f3f0f5;
    border: none;
    color: #4a3356;
    border-radius: 18px;
    font-size: 12px;
    font-weight: 500;
    font-family: 'DM Sans', sans-serif;
    flex: 1;
    padding: 12px;
    cursor: pointer;
    transition: background 0.2s;

    &:hover:not(:disabled) {
        background: #e9e4ed;
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
    background: linear-gradient(135deg, #ff8fab, #c4b5fd);
    border: none;
    color: #fff;
    border-radius: 18px;
    font-size: 12px;
    font-weight: 500;
    font-family: 'DM Sans', sans-serif;
    flex: 1;
    padding: 12px;
    cursor: pointer;
    transition: opacity 0.2s;

    &:hover:not(:disabled) {
        opacity: 0.9;
    }

    &:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }
}
</style>
