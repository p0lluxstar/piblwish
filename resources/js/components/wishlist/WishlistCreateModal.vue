<script setup lang="ts">
import { ChevronDown, ChevronUp, Link, Trash2 } from '@lucide/vue';
import { onMounted, onUnmounted, ref } from 'vue';

import { useItemReorder } from '../../composables/useItemReorder';
import { isValidItemUrl, normalizeItemUrl } from '../../lib/itemUrl';
import type {
    Wishlist,
    WishlistForm,
    WishlistItem,
} from '../../types/wishlist';
import LoaderButtonSpinner from '../ui/LoaderButtonSpinner.vue';
import ItemPriorityPicker from './ItemPriorityPicker.vue';
import WishlistColorPicker from './WishlistColorPicker.vue';
import WishlistSurpriseToggle from './WishlistSurpriseToggle.vue';

const props = defineProps<{
    isPending: boolean;
    // Список-источник при дублировании: форма заполняется его значениями
    source?: Wishlist | null;
}>();

const emit = defineEmits<{
    close: [];
    create: [payload: WishlistForm];
}>();

const defaultForm = (): WishlistForm => ({
    title: '',
    color: 'white',
    hideSelections: false,
    items: [
        {
            label: '',
            url: '',
            priority: null,
            isSelected: false,
        },
    ],
});

// Копия списка-источника без id позиций и выбора гостей: создаётся новый список
const sourceForm = (source: Wishlist): WishlistForm => ({
    title: `${source.title} (копия)`,
    color: source.color,
    hideSelections: source.hideSelections ?? false,
    items: source.items.length
        ? source.items.map((item) => ({
              label: item.label,
              url: item.url ?? '',
              priority: item.priority ?? null,
              isSelected: false,
          }))
        : defaultForm().items,
});

const form = ref(props.source ? sourceForm(props.source) : defaultForm());

// Индексы позиций с некорректной ссылкой; ошибка снимается, когда ссылку начинают править
const urlErrors = ref<boolean[]>([]);

const { itemKey, moveItem } = useItemReorder(() => form.value.items, urlErrors);

const addItem = (): void => {
    form.value.items.push({
        label: '',
        url: '',
        priority: null,
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
        hideSelections: form.value.hideSelections,
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
                <h2>
                    {{ props.source ? 'Дублировать список' : 'Создать список' }}
                </h2>

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
                    <WishlistSurpriseToggle v-model="form.hideSelections" />
                </div>

                <div class="form-group">
                    <label>Список желаний</label>

                    <div
                        v-for="(item, index) in form.items"
                        :key="itemKey(item)"
                        class="wishlist-item"
                    >
                        <div class="wishlist-item-fields">
                            <!-- Приоритет над полем описания, у левого края -->
                            <ItemPriorityPicker v-model="item.priority" />

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

                        <!-- Перестановка позиций: порядок сохраняется на сервере -->
                        <div v-if="form.items.length > 1" class="move-btns">
                            <button
                                class="move-btn"
                                type="button"
                                aria-label="Переместить выше"
                                :disabled="index === 0"
                                @click="moveItem(index, -1, $event)"
                            >
                                <ChevronUp :size="16" />
                            </button>

                            <button
                                class="move-btn"
                                type="button"
                                aria-label="Переместить ниже"
                                :disabled="index === form.items.length - 1"
                                @click="moveItem(index, 1, $event)"
                            >
                                <ChevronDown :size="16" />
                            </button>
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
