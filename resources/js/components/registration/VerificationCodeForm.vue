<script setup lang="ts">
import { useMutation } from '@tanstack/vue-query';
import { reactive } from 'vue';

import FormErrorMessage from '@/components/ui/FormErrorMessage.vue';
import LoaderButtonSpinner from '@/components/ui/LoaderButtonSpinner.vue';
import MainInputForm from '@/components/ui/MainInputForm.vue';
import PrimaryButton from '@/components/ui/PrimaryButton.vue';
import { api } from '@/lib/api';

const props = defineProps<{
    email: string;
}>();

const emit = defineEmits<{
    success: [];
}>();

const form = reactive({
    code: '',
});

const confirmCodeMutation = useMutation({
    mutationFn: (payload: { email: string; code: string }) =>
        api.post('/v1/verify-code', payload),
    onSuccess: () => {
        emit('success');
    },
});

const isLoading = confirmCodeMutation.isPending;
const isError = confirmCodeMutation.isError;

async function submitForm(): Promise<void> {
    // Сбрасываем прошлую ошибку и статус mutation перед новой отправкой формы
    confirmCodeMutation.reset();

    try {
        await confirmCodeMutation.mutateAsync({
            email: props.email,
            code: form.code,
        });
    } catch (error) {
        console.error(error);
    }
}
</script>

<template>
    <form class="verification-form" @submit.prevent="submitForm">
        <MainInputForm v-model="form.code" placeholder="Введите код" />

        <PrimaryButton :disabled="isLoading">
            <LoaderButtonSpinner v-if="isLoading" />
            <span v-else>Подтвердить</span>
        </PrimaryButton>

        <FormErrorMessage :show="isError">
            Не удалось подтвердить код. Попробуйте позже.
        </FormErrorMessage>
    </form>
</template>

<style scoped>
.verification-form {
    margin-top: 32px;
    max-width: 420px;
    display: flex;
    flex-direction: column;
    gap: 16px;
}
</style>
