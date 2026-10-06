<script setup lang="ts">
import { Eye } from '@lucide/vue';
import { computed } from 'vue';

// Модель — флаг режима сюрприза (hideSelections), а переключатель показывает
// обратное значение «Показывать выбранные подарки»: выключенное состояние
// соответствует сюрпризу, раскрыть выбор гостей владелец решает сам
const model = defineModel<boolean>({ required: true });

const showSelections = computed(() => !model.value);
</script>

<template>
    <!-- Кнопка, а не input: на поля ввода в модалке действуют стили .form-group input -->
    <button
        type="button"
        role="switch"
        :aria-checked="showSelections"
        :class="['surprise-toggle', { 'is-active': showSelections }]"
        @click="model = !model"
    >
        <span class="surprise-toggle-text">
            <span class="surprise-toggle-title">
                Показывать выбранные подарки
            </span>
            <!-- Значок глаза стоит в одной строке с подсказкой, перед текстом -->
            <span class="surprise-toggle-hint">
                <Eye
                    :size="12"
                    class="surprise-toggle-icon"
                    aria-hidden="true"
                />
                Вы увидите, какие подарки выбрали гости
            </span>
        </span>

        <span class="surprise-toggle-track" aria-hidden="true">
            <span class="surprise-toggle-thumb"></span>
        </span>
    </button>
</template>

<style scoped lang="scss">
.surprise-toggle {
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;
    padding: 6px 0;
    border: none;
    border-radius: 14px;
    background: none;
    text-align: left;
    cursor: pointer;

    &:focus-visible {
        outline: 2px solid var(--brand-violet, #8b5cf6);
        outline-offset: 2px;
    }
}

.surprise-toggle-icon {
    flex-shrink: 0;
    color: var(--brand-violet, #8b5cf6);
}

.surprise-toggle-text {
    // Занимает свободное место: переключатель прижат к правому краю
    display: flex;
    flex: 1;
    flex-direction: column;
    gap: 4px;
    min-width: 0;
}

// Как у подписей полей формы (.form-group label), например «Цвет списка»
.surprise-toggle-title {
    font-size: 13px;
    font-weight: 600;
    color: var(--ink, #241533);
}

.surprise-toggle-hint {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 10px;
    line-height: 1.3;
    color: #8a7a99;
}

.surprise-toggle-track {
    position: relative;
    flex-shrink: 0;
    width: 28px;
    height: 16px;
    border-radius: 14px;
    background: rgba(139, 92, 246, 0.2);
    transition: background 0.18s ease;
}

.surprise-toggle-thumb {
    position: absolute;
    top: 2px;
    left: 2px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: #fff;
    box-shadow: 0 1px 3px rgba(36, 21, 51, 0.25);
    transition: transform 0.18s ease;
}

.is-active .surprise-toggle-track {
    background: var(
        --brand-gradient,
        linear-gradient(135deg, #8b5cf6, #ec4899)
    );
}

.is-active .surprise-toggle-thumb {
    transform: translateX(12px);
}
</style>
