import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, Fragment, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import SessionExpirationMonitor from '@/Components/SessionExpirationMonitor.vue';
import AppDialogHost from '@/Components/AppDialogHost.vue';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        return createApp({
            render: () => h(Fragment, null, [
                h(App, props),
                h(SessionExpirationMonitor),
                h(AppDialogHost),
            ]),
        })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#d99b22',
        delay: 350,
        showSpinner: false,
    },
});
