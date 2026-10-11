<script setup lang="ts">
import type { JointGiftDraft } from '@/types/wishlist';

// Поля совместного подарка: при выборе позиции и при правке уже сохранённых данных
const draft = defineModel<JointGiftDraft>({ required: true });

defineProps<{ disabled?: boolean }>();
</script>

<template>
    <p class="joint-gift-hint">
        Другие гости увидят ваше имя и контакт и смогут присоединиться. Владелец
        списка этих данных не увидит.
    </p>

    <input
        v-model="draft.name"
        class="joint-gift-input"
        type="text"
        maxlength="50"
        required
        placeholder="Ваше имя, например «Иван, коллега Ани»"
        aria-label="Имя организатора"
        :disabled="disabled"
    />
    <input
        v-model="draft.contact"
        class="joint-gift-input"
        type="text"
        maxlength="100"
        placeholder="Контакт, например @ivan_k (необязательно)"
        aria-label="Контакт организатора"
        :disabled="disabled"
    />
    <input
        v-model="draft.comment"
        class="joint-gift-input"
        type="text"
        maxlength="200"
        placeholder="Комментарий, например «собираем до 10 октября»"
        aria-label="Комментарий"
        :disabled="disabled"
    />
</template>

<style scoped lang="scss">
.joint-gift-hint {
    margin: 0;
    font-size: 11px;
    line-height: 1.4;
    color: var(--ink-soft, #6b5878);
}

.joint-gift-input {
    width: 100%;
    padding: 6px 10px;
    border: 1px solid rgba(139, 92, 246, 0.2);
    border-radius: 10px;
    background: #fff;
    font-family: inherit;
    font-size: 12px;
    color: var(--ink, #241533);

    &:focus {
        outline: none;
        border-color: var(--brand-violet);
    }
}
</style>
