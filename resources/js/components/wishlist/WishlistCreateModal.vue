<script setup lang="ts">
import { Link, Trash2 } from '@lucide/vue';
import { onMounted, onUnmounted, ref } from 'vue';

import { isValidItemUrl, normalizeItemUrl } from '../../lib/itemUrl';
import type { WishlistForm, WishlistItem } from '../../types/wishlist';
import LoaderButtonSpinner from '../ui/LoaderButtonSpinner.vue';
import WishlistColorPicker from './WishlistColorPicker.vue';

const props = defineProps<{
    isPending: boolean;
}>();

const emit = defineEmits<{
    close: [];
    create: [payload: WishlistForm];
}>();

const defaultForm = (): WishlistForm => ({
    title: '',
    color: 'white',
    items: [
        {
            label: '',
            url: '',
            isSelected: false,
        },
    ],
});

const form = ref(defaultForm());

// Индексы позиций с некорректной ссылкой; ошибка снимается, когда ссылку начинают править
const urlErrors = ref<boolean[]>([]);

const addItem = (): void => {
    form.value.items.push({
        label: '',
        url: '',
        isSelected: false,
    });
};

const removeItem = (index: number): void => {
    form.value.items.splice(index, 1);
    urlErrors.value.splice(index, 1);
};

const clearUrlError = (index: number): void => {
    urlErrors.value[index] = false;
};

// Пустые позиции отбрасываются, ссылки нормализуются; null — в форме есть некорректная ссылка
const prepareItems = (): WishlistItem[] | null => {
    urlErrors.value = form.value.items.map((item) => {
        const url = normalizeItemUrl(item.url);

        return Boolean(item.label.trim() && url && !isValidItemUrl(url));
    });

    if (urlErrors.value.some(Boolean)) return null;

    return form.value.items
        .filter((item) => item.label.trim())
        .map((item) => ({ ...item, url: normalizeItemUrl(item.url) }));
};

const handleSubmit = (): void => {
    const items = prepareItems();

    if (!items) return;

    emit('create', {
        title: form.value.title,
        color: form.value.color,
        items,
    });
};

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
    urlErrors.value = [];
    enableBodyScroll();
    emit('close');
};
</script>

<template>
    <div class="modal-overlay">
        <!-- Фон модалки окрашивается в выбранный цвет: превью цвета списка -->
        <div :class="['modal', `wishlist-color--${form.color}`]">
            <div class="modal-header">
                <h2>Создать список</h2>

                <button class="close-btn" @click="closeModal"></button>
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
                    <label>Цвет списка</label>

                    <WishlistColorPicker v-model="form.color" />
                </div>

                <div class="form-group">
                    <label>Список желаний</label>

                    <div
                        v-for="(item, index) in form.items"
                        :key="index"
                        class="wishlist-item"
                    >
                        <div class="wishlist-item-fields">
                            <input
                                v-model="item.label"
                                type="text"
                                placeholder="Например: Книга"
                            />

                            <!-- Линия-уголок от поля описания: ссылка относится к этой позиции -->
                            <div class="item-url-row">
                                <Link
                                    :size="13"
                                    class="item-url-icon"
                                    aria-hidden="true"
                                />

                                <!-- type="text", а не "url": иначе браузер не пропустит адрес без https:// -->
                                <input
                                    v-model="item.url"
                                    type="text"
                                    inputmode="url"
                                    autocomplete="off"
                                    :class="[
                                        'item-url-input',
                                        {
                                            'item-url-input--error':
                                                urlErrors[index],
                                        },
                                    ]"
                                    placeholder="Ссылка на товар (необязательно)"
                                    :aria-invalid="
                                        urlErrors[index] || undefined
                                    "
                                    @input="clearUrlError(index)"
                                />
                            </div>

                            <span
                                v-if="urlErrors[index]"
                                class="item-url-error"
                            >
                                Некорректная ссылка
                            </span>
                        </div>

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
@use '../../../scss/ui/wishlistColors.scss';

// Превью цвета списка; для white — прежний белый фон модалки
.modal {
    background: var(--wishlist-bg, #fff);
    transition: background-color 0.2s ease;
}
</style>
