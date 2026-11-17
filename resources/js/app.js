import '@fontsource-variable/onest';
import '@fontsource-variable/unbounded';
import { createApp } from 'vue';
import PrimeVue from 'primevue/config';
import ToastService from 'primevue/toastservice';
import ConfirmationService from 'primevue/confirmationservice';
import { BookingPreset } from './theme.js';
import App from './App.vue';

createApp(App)
    .use(PrimeVue, {
        theme: { preset: BookingPreset },
        locale: { accept: 'Да', reject: 'Нет', aria: { close: 'Закрыть' } },
    })
    .use(ToastService)
    .use(ConfirmationService)
    .mount('#app');
