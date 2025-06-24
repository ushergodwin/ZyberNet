import "./bootstrap";

import "bootstrap/dist/css/bootstrap.min.css";
import "bootstrap";
import "../css/app.css";
import "@fortawesome/fontawesome-free/css/all.min.css";
import "@fortawesome/fontawesome-free/js/all.min.js";
import axios from "axios";
import Toast from "vue-toastification";
import { createApp, h } from "vue";
import { createInertiaApp } from "@inertiajs/vue3";
import { resolvePageComponent } from "laravel-vite-plugin/inertia-helpers";
import { ZiggyVue } from "../../vendor/tightenco/ziggy";
import "vue-toastification/dist/index.css";
import { usePage } from "@inertiajs/vue3";

const token = usePage().props?.auth?.user?.api_token;

if (token) {
    axios.defaults.headers.common["Authorization"] = `Bearer ${token}`;
}

const appName = import.meta.env.VITE_APP_NAME || "ZyberNet";

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob("./Pages/**/*.vue")
        ),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(Toast, {
                position: "bottom-left",
                timeout: 5000,
                closeOnClick: true,
                pauseOnHover: true,
                draggable: true,
                draggablePercent: 0.6,
                showCloseButtonOnHover: true,
                hideProgressBar: false,
                closeButton: "button",
                transition: "Vue-Toastification__bounce",
            })
            .mount(el);
    },
    progress: {
        color: "#4B5563",
    },
});

const csrfToken = document.head.querySelector('meta[name="csrf-token"]');
if (csrfToken) {
    axios.defaults.headers.common["X-CSRF-TOKEN"] = csrfToken.content;
}
