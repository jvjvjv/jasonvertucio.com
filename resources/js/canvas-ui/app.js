import { createApp } from 'vue';
import { createRouter, createWebHistory } from 'vue-router';
import { createHead } from '@unhead/vue/client';
import NProgress from 'nprogress';
import moment from 'moment';
import routes from './routes';
import App from './App.vue';

NProgress.configure({
    showSpinner: false,
    easing: 'ease',
    speed: 300,
});

const router = createRouter({
    history: createWebHistory('/canvas-ui'),
    routes,
});

router.beforeEach((to, from, next) => {
    NProgress.start();
    next();
});

const head = createHead();

const app = createApp(App);

// Make moment available globally via $moment
app.config.globalProperties.$moment = moment;

app.use(router);
app.use(head);

app.mount('#ui');
