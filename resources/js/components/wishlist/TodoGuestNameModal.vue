<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';

// Наибольшая длина имени, совпадает с CheckSharedTodoItemsRequest
const NAME_MAX_LENGTH = 50;

const props = defineProps<{
    // Владелец требует имя: без него отметить дела нельзя, кнопки «Пропустить» нет
    required: boolean;
    initialName: string;
    // check — окно открыто перед отметкой дел, edit — гость меняет имя
    mode: 'check' | 'edit';
}>();

const emit = defineEmits<{
    close: [];
    confirm: [name: string];
    skip: [];
}>();

const name = ref(props.initialName);
// Поле имени получает фокус при открытии окна
const input = ref<{ focus: () => void } | null>(null);

const trimmedName = computed(() => name.value.trim());

// Обязательное имя нельзя оставить пустым; необязательное в режиме edit
// можно стереть — тогда дела отмечаются без имени
const canConfirm = computed(
    () =>
        trimmedName.value !== '' || (!props.required && props.mode === 'edit'),
);

const confirm = (): void => {
    if (!canConfirm.value) return;

    emit('confirm', trimmedName.value);
};

const disableBodyScroll = (): void => {
    document.body.classList.add('modal-open');
};

const enableBodyScroll = (): void => {
    document.body.classList.remove('modal-open');
};

onMounted(() => {
    disableBodyScroll();
    input.value?.focus();
});

onUnmounted(() => {
    enableBodyScroll();
});
</script>

<template>
    <div class="modal-overlay" @click.self="emit('close')">
        <div
            class="modal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="guest-name-title"
        >
            <div class="modal-header">
                <h2 id="guest-name-title">Как вас зовут?</h2>
                <button
                    class="close-btn"
                    type="button"
                    aria-label="Закрыть"
                    @click="emit('close')"
                ></button>
            </div>

            <form @submit.prevent="confirm">
                <p class="hint">
                    Имя будет видно под делами, которые вы отметите: так
                    владелец и другие участники узнают, кто что сделал.
                    {{
                        required
                            ? 'Владелец списка просит указать имя.'
                            : 'Имя можно не указывать.'
                    }}
                </p>

                <div class="form-group">
                    <label for="guest-name">Ваше имя</label>
                    <input
                        id="guest-name"
                        ref="input"
                        v-model="name"
                        type="text"
                        :maxlength="NAME_MAX_LENGTH"
                        autocomplete="given-name"
                        placeholder="Например: Аня"
                    />
                </div>

                <div class="actions">
                    <button
                        v-if="!required && mode === 'check'"
                        type="button"
                        class="skip-btn"
                        @click="emit('skip')"
                    >
                        Пропустить
                    </button>

                    <button
                        type="submit"
                        class="create-btn"
                        :disabled="!canConfirm"
                    >
                        {{ mode === 'check' ? 'Отметить' : 'Сохранить' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<style scoped lang="scss">
@use '../../../scss/ui/wishlistModal.scss';

.hint {
    margin: 0 0 16px;
    font-size: 13px;
    line-height: 1.5;
    color: var(--ink-soft, #6b5878);
}

.actions {
    display: flex;
    gap: 12px;

    .create-btn {
        flex: 1;
    }
}

// Как «Отмена» в окне удаления списка
.skip-btn {
    flex: 1;
    padding: 12px;
    border: none;
    border-radius: 18px;
    background: rgba(139, 92, 246, 0.08);
    font-family: inherit;
    font-size: 13px;
    font-weight: 600;
    color: var(--ink, #241533);
    cursor: pointer;
    transition: all 0.2s ease;

    &:hover {
        background: rgba(139, 92, 246, 0.14);
    }
}
</style>
