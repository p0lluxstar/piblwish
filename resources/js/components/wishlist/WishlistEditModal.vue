<script setup lang="ts">
import { Trash2 } from '@lucide/vue';
import { onMounted, onUnmounted, ref, watch } from 'vue';

import { api } from '@/lib/api';

import type { Wishlist, WishlistItem } from '../../types/wishlist';
import LoaderButtonSpinner from '../ui/LoaderButtonSpinner.vue';

const props = defineProps<{
    wishlist: Wishlist;
    isPending: boolean;
}>();

const emit = defineEmits<{
    close: [];
    updated: [
        payload: {
            id: string;
            title: string;
            items: WishlistItem[];
        },
    ];
}>();

const defaultForm = (): Omit<Wishlist, 'id'> => ({
    title: '',
    items: [
        {
            isSelected: false,
            label: '',
        },
    ],
});

const form = ref<Omit<Wishlist, 'id'>>({
    title: '',
    items: [],
});

watch(
    () => props.wishlist,
    (wishlist) => {
        form.value = {
            title: wishlist.title,
            items: wishlist.items.map((item) => ({
                label: item.label,
                isSelected: item.isSelected ?? false,
            })),
        };
    },
    {
        immediate: true,
    },
);

const addItem = (): void => {
    form.value.items.push({
        isSelected: false,
        label: '',
    });
};

const removeItem = (index: number): void => {
    form.value.items.splice(index, 1);
};

const handleSubmit = async (): Promise<void> => {
    try {
        await api.patch(`/v1/wishlists/${props.wishlist.id}`, {
            title: form.value.title,
            items: form.value.items.filter((item) => item.label.trim()),
        });

        emit('updated', {
            id: props.wishlist.id,
            title: form.value.title,
            items: form.value.items.filter((item) => item.label.trim()),
        });

        emit('close');
    } catch (error) {
        console.error('Ошибка обновления списка:', error);
    }
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

const closeModal = (): void => {
    form.value = defaultForm();
    enableBodyScroll();
    emit('close');
};
</script>

<template>
    <div class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <h2>Редактировать список</h2>

                <button class="close-btn" @click="closeModal">×</button>
            </div>

            <form @submit.prevent="handleSubmit">
                <div class="form-group">
                    <label>Название списка</label>

                    <input
                        v-model="form.title"
                        type="text"
                        placeholder="Например: День рождения"
                    />
                </div>

                <div class="form-group">
                    <label>Список желаний</label>

                    <div
                        v-for="(item, index) in form.items"
                        :key="index"
                        class="wishlist-item"
                    >
                        <label class="checkbox-wrapper">
                            <input
                                type="checkbox"
                                v-model="item.isSelected"
                                class="checkbox-input"
                            />

                            <span class="checkbox-custom"></span>
                        </label>

                        <input
                            v-model="item.label"
                            type="text"
                            placeholder="Например: Книга"
                        />

                        <button
                            class="remove-btn"
                            type="button"
                            @click="removeItem(index)"
                        >
                            <Trash2 />
                        </button>
                    </div>

                    <button class="add-item-btn" type="button" @click="addItem">
                        + Добавить желание
                    </button>
                </div>

                <button
                    type="submit"
                    :disabled="props.isPending"
                    class="create-btn"
                >
                    <LoaderButtonSpinner v-if="props.isPending" :size="18" />

                    <span v-else>Сохранить</span>
                </button>
            </form>
        </div>
    </div>
</template>

<style scoped lang="scss">
@use '../../../scss/ui/checkboxCard';
@use '../../../scss/ui/wishlistModal.scss';

.wishlist-item input[type='text'] {
    flex-grow: 1;
    width: auto;
}
</style>
