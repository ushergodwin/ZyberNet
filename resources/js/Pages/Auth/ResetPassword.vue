<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    email: String,
    token: String,
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('password.update'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>

    <Head title="Reset Password" />

    <section class="login-bg d-flex align-items-center justify-content-center min-vh-100">
        <section class="login-card-wrapper w-100">
            <div class="login-card card card-body shadow-lg border-0 mx-auto">
                <!-- Logo -->
                <div class="text-center mb-4" @click="$inertia.visit('/')" role="button">
                    <img src="@/assets/images/superspotwifi-logo.png" alt="SuperSpot Wifi Logo"
                        class="img-fluid rounded-circle logo" />
                </div>
                <form @submit.prevent="submit">
                    <div>
                        <InputLabel for="email" value="Email" />
                        <TextInput id="email" v-model="form.email" type="email" class="mt-1 block w-full" required
                            autofocus autocomplete="username" />
                        <InputError class="mt-2" :message="form.errors.email" />
                    </div>

                    <div class="mt-4">
                        <InputLabel for="password" value="Password" />
                        <TextInput id="password" v-model="form.password" type="password" class="mt-1 block w-full"
                            required autocomplete="new-password" />
                        <InputError class="mt-2" :message="form.errors.password" />
                    </div>

                    <div class="mt-4">
                        <InputLabel for="password_confirmation" value="Confirm Password" />
                        <TextInput id="password_confirmation" v-model="form.password_confirmation" type="password"
                            class="mt-1 block w-full" required autocomplete="new-password" />
                        <InputError class="mt-2" :message="form.errors.password_confirmation" />
                    </div>

                    <div class="flex items-center justify-end mt-4">
                        <button
                            class="btn btn-gradient w-100 py-2 fw-semibold d-flex align-items-center justify-content-center"
                            :disabled="form.processing">
                            Reset Password
                        </button>
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
