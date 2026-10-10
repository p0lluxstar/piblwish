<script setup lang="ts">
import { computed, onMounted, onUnmounted } from 'vue';

import { lockBodyScroll, unlockBodyScroll } from '../../lib/bodyScrollLock';
import LoaderButtonSpinner from '../ui/LoaderButtonSpinner.vue';

const props = defineProps<{
    // Число карточек в архиве
    count: number;
    isPending: boolean;
}>();

const emit = defineEmits<{
    close: [];
    confirm: [];
}>();

// «1 карточку», «2 карточки», «5 карточек»
const countText = computed(() => {
    const lastTwo = props.count % 100;
    const last = props.count % 10;

    if (lastTwo >= 11 && lastTwo <= 14) return `${props.count} карточек`;
    if (last === 1) return `${props.count} карточку`;
    if (last >= 2 && last <= 4) return `${props.count} карточки`;

    return `${props.count} карточек`;
});

const closeModal = (): void => {
    if (props.isPending) return;

    emit('close');
};

onMounted(lockBodyScroll);
onUnmounted(unlockBodyScroll);
</script>

<template>
    <div class="modal-overlay" @click.self="closeModal">
        <div class="modal">
            <div class="modal-header">
                <h2>Очистка архива</h2>
                <button class="close-btn" @click="closeModal"></button>
            </div>

            <div class="modal-body">
                <p>
                    Вы действительно хотите удалить из архива
                    <strong>{{ countText }}</strong>
                    ?
                </p>
                <p class="warning-text">
                    Это действие необратимо: карточки будут удалены вместе с
                    позициями и выбором гостей.
                </p>
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
                    @click="emit('confirm')"
                >
                    <LoaderButtonSpinner v-if="props.isPending" :size="18" />
                    <span v-else>Удалить все</span>
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
