import 'normalize.css';
import '../scss/app.scss';

import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query';
import { createPinia } from 'pinia';
import { createApp } from 'vue';

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

app.use(router).use(pinia).use(VueQueryPlugin, { queryClient }).mount('#app');
