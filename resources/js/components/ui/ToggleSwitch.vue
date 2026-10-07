<script setup lang="ts">
import type { Component } from 'vue';

// Переключатель с заголовком и подсказкой, как в окнах создания и редактирования списка.
// Модель — состояние переключателя: true — включён
const model = defineModel<boolean>({ required: true });

defineProps<{
    title: string;
    hint: string;
    // Значок перед подсказкой (иконка из @lucide/vue)
    icon: Component;
}>();
</script>

<template>
    <!-- Кнопка, а не input: на поля ввода в модалке действуют стили .form-group input -->
    <button
        type="button"
        role="switch"
        :aria-checked="model"
        :class="['toggle-switch', { 'is-active': model }]"
        @click="model = !model"
    >
        <span class="toggle-switch-text">
            <span class="toggle-switch-title">
                {{ title }}
            </span>
            <!-- Значок стоит в одной строке с подсказкой, перед текстом -->
            <span class="toggle-switch-hint">
                <component
                    :is="icon"
                    :size="12"
                    class="toggle-switch-icon"
                    aria-hidden="true"
                />
                {{ hint }}
            </span>
        </span>

        <span class="toggle-switch-track" aria-hidden="true">
            <span class="toggle-switch-thumb"></span>
        </span>
    </button>
</template>

<style scoped lang="scss">
.toggle-switch {
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

.toggle-switch-icon {
    flex-shrink: 0;
    color: var(--brand-violet, #8b5cf6);
}

.toggle-switch-text {
    // Занимает свободное место: переключатель прижат к правому краю
    display: flex;
    flex: 1;
    flex-direction: column;
    gap: 4px;
    min-width: 0;
}

// Как у подписей полей формы (.form-group label), например «Цвет списка»
.toggle-switch-title {
    font-size: 13px;
    font-weight: 600;
    color: var(--ink, #241533);
}

.toggle-switch-hint {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 10px;
    line-height: 1.3;
    color: #8a7a99;
}

.toggle-switch-track {
    position: relative;
    flex-shrink: 0;
    width: 28px;
    height: 16px;
    border-radius: 14px;
    background: rgba(139, 92, 246, 0.2);
    transition: background 0.18s ease;
}

.toggle-switch-thumb {
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

.is-active .toggle-switch-track {
    background: var(
        --brand-gradient,
        linear-gradient(135deg, #8b5cf6, #ec4899)
    );
}

.is-active .toggle-switch-thumb {
    transform: translateX(12px);
}
</style>
