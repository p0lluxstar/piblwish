<script setup lang="ts">
import { ChevronDown, ChevronUp, Gift, Link, Trash2 } from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

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
    wishlist: Wishlist;
    isPending: boolean;
}>();

const emit = defineEmits<{
    close: [];
    update: [payload: WishlistForm & { id: string }];
}>();

const defaultForm = (): WishlistForm => ({
    title: '',
    color: 'white',
    hideSelections: false,
    items: [
        {
            isSelected: false,
            label: '',
        },
    ],
});

const form = ref<WishlistForm>({
    title: '',
    color: 'white',
    hideSelections: false,
    items: [],
});

// Индексы позиций с некорректной ссылкой; ошибка снимается, когда ссылку начинают править
const urlErrors = ref<boolean[]>([]);

watch(
    () => props.wishlist,
    (wishlist) => {
        form.value = {
            title: wishlist.title,
            color: wishlist.color,
            hideSelections: wishlist.hideSelections ?? false,
            // id нужен серверу, чтобы в режиме сюрприза сохранить выбор гостей
            items: wishlist.items.map((item) => ({
                id: item.id,
                label: item.label,
                url: item.url ?? '',
                priority: item.priority ?? null,
                isSelected: item.isSelected ?? false,
            })),
        };
    },
    {
        immediate: true,
    },
);

// Чекбоксы выбора показываются, только если владелец видит выбор гостей:
// при скрытом выборе сервер не принимает isSelected из формы
const showSelection = computed(
    () => !props.wishlist.hideSelections && !form.value.hideSelections,
);

const { itemKey, moveItem } = useItemReorder(() => form.value.items, urlErrors);

const addItem = (): void => {
    form.value.items.push({
        isSelected: false,
        label: '',
        url: '',
        priority: null,
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

// Запрос выполняет WishlistMain (мутация updateWishlist): там же состояние
// загрузки для кнопки и закрытие модалки после успешного сохранения
const handleSubmit = (): void => {
    const items = prepareItems();

    if (!items) return;

    emit('update', {
        id: props.wishlist.id,
        title: form.value.title,
        color: form.value.color,
        hideSelections: form.value.hideSelections,
        items,
    });
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
                <h2>Редактировать список</h2>

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
                        <!-- Выбранная гостем позиция: значок подарка на сером фоне, как в карточке.
                             Повторный клик снимает выбор -->
                        <label v-if="showSelection" class="checkbox-wrapper">
                            <input
                                type="checkbox"
                                v-model="item.isSelected"
                                class="checkbox-input"
                                :aria-label="
                                    item.isSelected
                                        ? 'Забронировано, снять выбор'
                                        : 'Отметить как выбранное'
                                "
                            />

                            <span class="checkbox-custom">
                                <Gift v-if="item.isSelected" :size="11" />
                            </span>
                        </label>

                        <div class="wishlist-item-fields">
                            <!-- Приоритет над полем описания, у левого края -->
                            <ItemPriorityPicker
                                v-model="item.priority"
                                :muted="showSelection && item.isSelected"
                            />

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

                    <span v-else>Сохранить</span>
                </button>
            </form>
        </div>
    </div>
</template>

<style scoped lang="scss">
@use '../../../scss/ui/checkboxCard';
@use '../../../scss/ui/wishlistModal.scss';
@use '../../../scss/ui/wishlistColors.scss';

// Превью цвета списка; для white — прежний белый фон модалки
.modal {
    background: var(--wishlist-bg, #fff);
    transition: background-color 0.2s ease;
}

.wishlist-item input[type='text'] {
    flex-grow: 1;
    width: auto;
}

// По центру поля описания (см. .wishlist-item в wishlistModal.scss)
.wishlist-item > .checkbox-wrapper {
    flex-shrink: 0;
    margin-top: calc(var(--item-label-offset) + 12px);
}

// Вместо галочки из checkboxCard.scss — значок подарка на сером градиенте
.checkbox-input:checked + .checkbox-custom {
    background: linear-gradient(135deg, #dbe2ea, #64748b);
    color: #fff;

    &::after {
        content: none;
    }
}
</style>
