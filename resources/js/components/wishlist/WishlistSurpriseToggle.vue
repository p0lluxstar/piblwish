<script setup lang="ts">
import { EyeOff } from '@lucide/vue';

// Режим сюрприза: владелец не видит, какие позиции выбрали гости
const model = defineModel<boolean>({ required: true });
</script>

<template>
    <!-- Кнопка, а не input: на поля ввода в модалке действуют стили .form-group input -->
    <button
        type="button"
        role="switch"
        :aria-checked="model"
        :class="['surprise-toggle', { 'is-active': model }]"
        @click="model = !model"
    >
        <span class="surprise-toggle-text">
            <span class="surprise-toggle-title">Режим сюрприза</span>
            <!-- Значок глаза стоит в одной строке с описанием -->
            <span class="surprise-toggle-hint">
                <EyeOff
                    :size="12"
                    class="surprise-toggle-icon"
                    aria-hidden="true"
                />
                Не показывать мне, какие подарки уже выбрали гости
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
