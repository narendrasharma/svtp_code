import '../css/app.css';
import './bootstrap';
import 'bootstrap/dist/js/bootstrap.bundle.min.js';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';

createInertiaApp({
    progress: {
        delay: 200,
        color: '#BE185D',
        includeCSS: true,
        showSpinner: false,
    },
    resolve: (name) =>
        resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        // Phase 13A: keep <html lang/dir> in sync with the shared
        // localization contract without flicker (server rendered first).
        try {
            const localization = props.initialPage?.props?.localization;
            if (localization?.locale) {
                document.documentElement.setAttribute('lang', localization.locale);
            }
            if (localization?.direction) {
                document.documentElement.setAttribute('dir', localization.direction);
            }
        } catch (e) { /* non-fatal */ }

        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
});
