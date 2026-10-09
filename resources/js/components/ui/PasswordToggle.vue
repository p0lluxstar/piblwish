<script setup lang="ts">
import { Eye, EyeOff } from '@lucide/vue';

// Кнопка «показать пароль» внутри поля; родитель задаёт полю position: relative
// и отступ справа, чтобы текст не заходил под иконку
withDefaults(defineProps<{ size?: number }>(), { size: 20 });

const visible = defineModel<boolean>({ required: true });
</script>

<template>
    <!-- tabindex="-1": Tab ведёт из поля сразу к следующему полю,
         mousedown.prevent оставляет фокус и курсор в поле -->
    <button
        type="button"
        class="password-toggle"
        tabindex="-1"
        :aria-label="visible ? 'Скрыть пароль' : 'Показать пароль'"
        :aria-pressed="visible"
        @mousedown.prevent
        @click="visible = !visible"
    >
        <EyeOff v-if="visible" :size="size" />
        <Eye v-else :size="size" />
    </button>
</template>

<style scoped>
.password-toggle {
    position: absolute;
    top: 50%;
    right: 10px;
    transform: translateY(-50%);
    display: grid;
    place-items: center;
    padding: 6px;
    border: none;
    border-radius: 10px;
    background: transparent;
    color: #a1849f;
    cursor: pointer;
    transition:
        color 0.2s ease,
        background 0.2s ease;
}

.password-toggle:hover {
    color: var(--brand-violet, #8b5cf6);
    background: rgba(139, 92, 246, 0.08);
}
</style>
