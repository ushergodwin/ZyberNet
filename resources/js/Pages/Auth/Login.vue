<template>
    <div class="login-bg d-flex align-items-center justify-content-center min-vh-100">

        <Head title="Admin Login" />

        <section class="login-card-wrapper w-100">
            <div class="login-card card card-body shadow-lg border-0 mx-auto">
                <form @submit.prevent="submit">
                    <!-- Logo -->
                    <div class="text-center mb-4" @click="$inertia.visit('/')" role="button">
                        <img src="@/assets/images/superspotwifi-logo.png" alt="SuperSpot Wifi Logo"
                            class="img-fluid rounded-circle logo" />
                    </div>

                    <!-- Title -->
                    <h2 class="text-center text-white mb-4">Admin Panel</h2>

                    <!-- Email -->
                    <div class="mb-3">
                        <InputLabel for="email" value="Email" />
                        <TextInput id="email" v-model="form.email" type="email" class="mt-1 block w-full input-rounded"
                            required autofocus autocomplete="username" />
                        <InputError class="mt-2" :message="form.errors.email" />
                    </div>

                    <!-- Password -->
                    <div class="mb-3">
                        <label for="password" class="form-label">
                            Password &nbsp;
                            <a href="javascript:void(0)" @click="togglePassword" class="text-light">
                                <i :key="passwordInputType"
                                    :class="['fas', passwordInputType === 'password' ? 'fa-eye' : 'fa-eye-slash']">
                                </i>
                            </a>

                        </label>
                        <TextInput id="password" v-model="form.password" :type="passwordInputType"
                            class="mt-1 block w-full input-rounded" required autocomplete="current-password" />
                        <InputError class="mt-2" :message="form.errors.password" />
                    </div>

                    <!-- Submit -->
                    <div class="d-flex justify-content-center align-items-center mt-4">
                        <button
                            class="btn btn-gradient w-100 py-2 fw-semibold d-flex align-items-center justify-content-center"
                            :disabled="form.processing">
                            <span v-if="form.processing">
                                <i class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></i>
                                Logging in...
                            </span>
                            <span v-else>
                                <i class="fas fa-lock me-2"></i> Log in
                            </span>
                        </button>
                    </div>
                    <div class="d-flex justify-content-center align-items-center mt-4">
                        <a href="javascript:void(0)" @click="forgotPassword">
                            Forgot Password?
                        </a>
                    </div>
                </form>
            </div>
        </section>
    </div>
</template>

<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { ref } from 'vue';

defineProps({
    canResetPassword: Boolean,
    status: String,
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.transform(data => ({
        ...data,
        remember: form.remember ? 'on' : '',
    })).post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};

const passwordInputType = ref('password');

const togglePassword = () => {
    passwordInputType.value = passwordInputType.value === 'password' ? 'text' : 'password';
}

const forgotPassword = () => {
    router.visit('/forgot-password')
}
</script>

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
