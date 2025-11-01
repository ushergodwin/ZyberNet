<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';

defineProps({
    status: String,
});

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};

const back = () => {
    router.visit('/login');
}
</script>

<template>

    <Head title="Forgot Password" />


    <section class="login-bg d-flex align-items-center justify-content-center min-vh-100">
        <section class="login-card-wrapper w-100">
            <div class="login-card card card-body shadow-lg border-0 mx-auto">
                <!-- Logo -->
                <div class="text-center mb-4" @click="$inertia.visit('/')" role="button">
                    <img src="@/assets/images/superspotwifi-logo.png" alt="SuperSpot Wifi Logo"
                        class="img-fluid rounded-circle logo" />
                </div>
                <span class="mb-4 text-sm">
                    Forgot your password? No problem. Just let us know your email address and we will email you a
                    password
                    reset
                    link
                    that will allow you to choose a new one.
                </span>

                <div v-if="status" class="mb-4 font-medium text-sm text-green-600">
                    {{ status }}
                </div>

                <form @submit.prevent="submit">

                    <div>
                        <InputLabel for="email" value="Email" />
                        <TextInput id="email" v-model="form.email" type="email" class="mt-1 block w-full" required
                            autofocus autocomplete="username" />
                        <InputError class="mt-2" :message="form.errors.email" />
                    </div>

                    <div class="flex items-center justify-end mt-4">
                        <button
                            class="btn btn-gradient w-100 py-2 fw-semibold d-flex align-items-center justify-content-center"
                            :disabled="form.processing">
                            Email Password Reset Link
                        </button>
                    </div>
                    <div class="d-flex justify-content-end mt-4"> <a href="javascript:void(0)" @click="back">
                            Back to Login Page
                        </a>
                    </div>
                </form>
            </div>
        </section>
    </section>
</template>
<style scoped>
.login-bg {
    background: linear-gradient(135deg, #5327ef 0%, #ff5f6d 100%) !important;
    background-attachment: fixed;
}

.login-card-wrapper {
    max-width: 400px;
    width: 100%;
    padding: 2rem 0;
}

.login-card {
    background: #0d0c20;
    border-radius: 20px;
    padding: 2rem;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
    color: #fff;
}

.logo {
    width: 100px;
}

.input-rounded {
    border-radius: 30px;
    padding: 0.5rem 1rem;
}

.btn-gradient {
    background: linear-gradient(90deg, #00f0ff, #ff00c8);
    border: none;
    border-radius: 30px;
    color: white;
    transition: all 0.3s ease;
}

.btn-gradient:hover {
    opacity: 0.9;
}

@media (max-width: 600px) {
    .login-card-wrapper {
        padding: 1rem 0;
    }

    .login-card {
        padding: 1.25rem 1rem;
    }
}
</style>
