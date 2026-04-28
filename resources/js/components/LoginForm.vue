<script setup lang="ts">
import axios from 'axios';
import { ref } from 'vue';

interface User {
    id: string;
    username: string;
    email: string;
}

axios.defaults.baseURL = 'http://localhost:8000';
axios.defaults.withCredentials = true;
axios.defaults.withXSRFToken = true;

const user = ref<User | null>(null);

const form = ref({
    email: '',
    password: '',
});

const loading = ref(false);
const error = ref('');

async function login(): Promise<void> {
    error.value = '';
    loading.value = true;

    try {
        // 1 Получаем CSRF cookie
        await axios.get('/sanctum/csrf-cookie');
        axios.defaults.withCredentials = true;
        axios.defaults.withXSRFToken = true;

        // 2 Логин
        const response = await axios.post(
            '/api/v1/login',
            {
                email: form.value.email,
                password: form.value.password,
            },
            {
                headers: {
                    Accept: 'application/json',
                },
            },
        );

        user.value = response.data.data;
    } catch (e) {
        if (e.response?.status === 422) {
            error.value = 'Неверный логин или пароль';
        } else {
            error.value = 'Ошибка авторизации';
        }
    } finally {
        loading.value = false;
    }
}

async function logout(): Promise<void> {
    try {
        await axios.post(
            '/logout',
            {},
            {
                headers: {
                    Accept: 'application/json',
                },
            },
        );

        user.value = null;

        form.value.email = '';
        form.value.password = '';
    } catch {
        error.value = 'Ошибка выхода';
    }
}
</script>

<template>
    <div class="auth-box">
        <div v-if="!user">
            <p class="eyebrow">Вход</p>
            <h2>Создать аккаунт</h2>

            <input
                v-model="form.email"
                type="email"
                placeholder="Логин (email)"
            />

            <input
                v-model="form.password"
                type="password"
                placeholder="Пароль"
            />

            <button :disabled="loading" @click="login">
                {{ loading ? 'Входим...' : 'Войти' }}
            </button>

            <p v-if="error" class="error">
                {{ error }}
            </p>
        </div>

        <div v-else>
            <h2>Привет, {{ user.username }}</h2>

            <button @click="logout">Выход</button>
        </div>
    </div>
</template>

<style scoped>
.auth-box {
    width: 320px;
    margin: 40px auto;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

input {
    display: block;
    width: 100%;
    margin-bottom: 10px;
    padding: 10px;
}

button {
    padding: 10px 16px;
    cursor: pointer;
}

.error {
    color: red;
}
</style>
