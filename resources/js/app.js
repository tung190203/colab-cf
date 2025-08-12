import './bootstrap';
import { createApp } from 'vue';
import App from './App.vue';
import router from './routers/index.js';
import Vue3Toastify from 'vue3-toastify'
import 'vue3-toastify/dist/index.css'

const app = createApp(App);
app.use(router);
app.use(Vue3Toastify, {
    position: 'top-right',
    autoClose: 3000,
    hideProgressBar: false,
    closeOnClick: true,
    pauseOnHover: true,
    draggable: true,
    progress: undefined,
})
app.mount('#app');
