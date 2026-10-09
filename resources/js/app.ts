import 'normalize.css';
// Рукописный шрифт текста заметок; браузер загружает только нужные наборы символов
import '@fontsource-variable/shantell-sans';
import '../scss/app.scss';

import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query';
import { createPinia } from 'pinia';
import { createApp } from 'vue';

import { setupAuthInterceptor } from '@/lib/authInterceptor';

import App from './App.vue';
import router from './router';

const queryClient = new QueryClient({
    defaultOptions: {
        queries: {
            retry: 2,
            refetchOnWindowFocus: false,
        },
    },
});

const app = createApp(App);

const pinia = createPinia();

app.use(router).use(pinia).use(VueQueryPlugin, { queryClient });

setupAuthInterceptor(router, queryClient);

app.mount('#app');
