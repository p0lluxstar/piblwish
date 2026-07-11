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

.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.45);

    display: flex;
    align-items: center;
    justify-content: center;

    z-index: 1000;
}

.modal {
    width: 100%;
    max-width: 420px;

    background: white;
    border-radius: 16px;

    padding: 24px;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;

    margin-bottom: 20px;
}

.close-btn {
    border: none;
    background: transparent;

    font-size: 24px;
    cursor: pointer;
}

.form-group {
    display: flex;
    flex-direction: column;
    margin-bottom: 16px;
}

.form-group label {
    margin-bottom: 8px;
    font-weight: 500;
}

.form-group input {
    padding: 12px;
    border: 1px solid #ddd;
    border-radius: 10px;
    font-size: 14px;
}

.wishlist-item input[type='text'] {
    flex-grow: 1;
    width: auto;
}

.wishlist-item {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
}

.remove-btn {
    border: none;
    background: transparent;
    color: #b3b3b3;
    cursor: pointer;

    &:hover {
        color: #ff8fab;
    }
}

.add-item-btn {
    background: transparent;
    border: none;
    color: #c4b5fd;
    font-size: 14px;
    cursor: pointer;

    &:hover {
        color: #ff8fab;
    }
}

.create-btn {
    display: flex;
    justify-content: center;
    background: linear-gradient(135deg, #ff8fab, #c4b5fd);
    border: none;
    color: #fff;
    border-radius: 18px;
    font-size: 12px;
    font-weight: 500;
    font-family: 'DM Sans', sans-serif;
    width: 100%;
    padding: 12px;
    cursor: pointer;
}
</style>
