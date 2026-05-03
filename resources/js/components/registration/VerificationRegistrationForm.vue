<script setup lang="ts">
import { useMutation } from '@tanstack/vue-query';
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue';

import FormErrorMessage from '@/components/ui/FormErrorMessage.vue';
import InputRegistationForms from '@/components/ui/InputRegistationForms.vue';
import LoaderButtonSpinner from '@/components/ui/LoaderButtonSpinner.vue';
import PrimaryButton from '@/components/ui/PrimaryButton.vue';
import { api } from '@/lib/api';

const props = defineProps<{
    email: string;
}>();

const emit = defineEmits<{
    success: [];
    resend: []; // 👈 добавим событие для повторной отправки
}>();

const form = reactive({
    code: '',
});

// таймер
const timeLeft = ref(120);
const timer = ref<number | null>(null);

const isExpired = computed(() => timeLeft.value <= 0);

function startTimer(): void {
    timeLeft.value = 120; // 2 минуты

    timer.value = setInterval(() => {
        if (timeLeft.value > 0) {
            timeLeft.value--;
        } else {
            stopTimer();
        }
    }, 1000);
}

function stopTimer(): void {
    if (timer.value) {
        clearInterval(timer.value);
        timer.value = null;
    }
}

// формат mm:ss
const formattedTime = computed(() => {
    const minutes = Math.floor(timeLeft.value / 60);
    const seconds = timeLeft.value % 60;

    return `${minutes}:${seconds.toString().padStart(2, '0')}`;
});

onMounted(startTimer);
onUnmounted(stopTimer);

const confirmCodeMutation = useMutation({
    mutationFn: (payload: { email: string; code: string }) =>
        api.post('/v1/verify-registration', payload),
    onSuccess: () => {
        emit('success');
    },
});

const isLoading = confirmCodeMutation.isPending;
const isError = confirmCodeMutation.isError;

async function submitForm(): Promise<void> {
    confirmCodeMutation.reset();

    if (isExpired.value) return; // защита

    try {
        await confirmCodeMutation.mutateAsync({
            email: props.email,
            code: form.code,
        });
    } catch (error) {
        console.error(error);
    }
}

// повторная отправка
function resendCode(): void {
    emit('resend');
    startTimer(); // перезапускаем таймер
}
</script>

<template>
    <div class="header">
        <p class="eyebrow">Подтверждение регистрации</p>
    </div>

    <h2>Введите код подтверждения</h2>
    <form class="verification-form" @submit.prevent="submitForm">
        <InputRegistationForms v-model="form.code" placeholder="Введите код" />

        <p v-if="!isExpired" class="timer-text">
            Код действует: {{ formattedTime }}
        </p>

        <PrimaryButton v-if="!isExpired" :disabled="isLoading">
            <LoaderButtonSpinner v-if="isLoading" />
            <span v-else>Подтвердить</span>
        </PrimaryButton>

        <PrimaryButton v-else @click.prevent="resendCode">
            Отправить новый код
        </PrimaryButton>

        <FormErrorMessage :show="isError">
            Не удалось подтвердить код. Попробуйте позже.
        </FormErrorMessage>
    </form>
</template>

<style scoped>
.verification-form {
    position: relative;
    margin-top: 32px;
    max-width: 420px;
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.timer-text {
    position: absolute;
    max-width: 420px;
    top: -20px;
    right: 10px;
    color: #6b7280;
    font-size: 14px;
    text-align: right;
    font-style: italic;
}
</style>
