import axios from "axios";
import { usePage } from "@inertiajs/vue3";
import { swalNotification } from "./helpers.mixin";

axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
const token = usePage().props.auth.token;
if (token) {
    axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
}

export default {
    data() {
        return {
            processing: false,
        };
    },
    methods: {
        post(url, params, successCallback, errorCallback) {
            this.processing = true;
            axios
                .post(url, params)
                .then((response) => {
                    this.processing = false;
                    // Check if a callback function is provided and execute it
                    if (typeof successCallback === "function") {
                        successCallback(response);
                    }
                })
                .catch((error) => {
                    this.processing = false;

                    if (error.response && error.response.status === 403) {
                        return swalNotification(
                            "error",
                            "You don't have enough permissions to proceed!"
                        );
                    } else if (typeof errorCallback === "function") {
                        errorCallback(error);
                    }
                });
        },
        get(url, successCallback, errorCallback) {
            this.processing = true;
            axios
                .get(url)
                .then((response) => {
                    this.processing = false;
                    // Check if a callback function is provided and execute it
                    if (typeof successCallback === "function") {
                        successCallback(response);
                    }
                })
                .catch((error) => {
                    this.processing = false;

                    if (error.response && error.response.status === 403) {
                        return swalNotification(
                            "error",
                            "You don't have enough permissions to proceed!"
                        );
                    } else if (typeof errorCallback === "function") {
                        errorCallback(error);
                    }
                });
        },
    },
};
