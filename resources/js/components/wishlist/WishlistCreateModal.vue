<script setup lang="ts">
import { Trash2 } from '@lucide/vue';
import { ref } from 'vue';

import type { Wishlist, WishlistItem } from '../../types/wishlist';
import LoaderButtonSpinner from '../ui/LoaderButtonSpinner.vue';

const props = defineProps<{
    isPending: boolean;
}>();

const emit = defineEmits<{
    close: [];
    create: [
        payload: {
            title: string;
            items: WishlistItem[];
        },
    ];
}>();

const defaultForm = (): Omit<Wishlist, 'id'> => ({
    title: '',
    items: [
        {
            label: '',
            isSelected: false,
        },
    ],
});

const form = ref(defaultForm());

const addItem = (): void => {
    form.value.items.push({
        label: '',
        isSelected: false,
    });
};

const removeItem = (index: number): void => {
    form.value.items.splice(index, 1);
};

const handleSubmit = (): void => {
    emit('create', {
        title: form.value.title,
        items: form.value.items.filter((item) => item.label.trim()),
    });
};

const closeModal = (): void => {
    form.value = defaultForm();

    emit('close');
};
</script>

<template>
    <div class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <h2>Создать список</h2>

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

                    <span v-else>Создать</span>
                </button>
            </form>
        </div>
    </div>
</template>

<style scoped lang="scss">
@use '../../../scss/ui/wishlistModal.scss';
</style>
