<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticationCard from '@/Components/AuthenticationCard.vue';
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo.vue';
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import wifiLogo from '@/assets/images/wifi-windows.png';
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
</script>

<template>
    <div class="login-bg d-flex align-items-center justify-content-center min-vh-100">

        <Head title="Log in" />
        <section class="login-card-wrapper w-100">
            <div class="login-card card card-body shadow-lg border-0 mx-auto">
                <form @submit.prevent="submit">
                    <div class="text-center mb-4" @click="$inertia.visit('/')" role="button">
                        <img src="@/assets/images/zybernet-short-logo.png" alt="logo" class="img-fluid rounded-circle"
                            width="100">
                    </div>
                    <div>
                        <InputLabel for="email" value="Email" />
                        <TextInput id="email" v-model="form.email" type="email" class="mt-1 block w-full" required
                            autofocus autocomplete="username" />
                        <InputError class="mt-2" :message="form.errors.email" />
                    </div>
                    <div class="mt-4">
                        <InputLabel for="password" value="Password" />
                        <TextInput id="password" v-model="form.password" type="password" class="mt-1 block w-full"
                            required autocomplete="current-password" />
                        <InputError class="mt-2" :message="form.errors.password" />
                    </div>
                    <!-- <div class="block mt-4">
                        <label class="flex items-center">
                            <Checkbox v-model:checked="form.remember" name="remember" />
                        </label>
                    </div> -->
                    <div class="d-flex justify-content-center align-items-center mt-4">
                        <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                            <span v-if="form.processing">
                                <i class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></i>
                                Loading...
                            </span>
                            <span v-else>Log in</span>
                        </PrimaryButton>
                        <!-- <Link v-if="canResetPassword" :href="route('password.request')" class="text-decoration-none"> -->
                        <!-- Forgot your password?</Link> -->
                    </div>
                </form>
            </div>
        </section>
    </div>
</template>

<style scoped>
.login-bg {
    min-height: 100vh;
    min-width: 100vw;
    background: linear-gradient(135deg, #5327ef 0%, #ff5f6d 100%);
    background-attachment: fixed;
    display: flex;
    align-items: center;
    justify-content: center;
}

.login-card-wrapper {
    max-width: 400px;
    width: 100%;
    padding: 2rem 0;
}

.login-card {
    border-radius: 1.25rem;
    background: rgba(255, 255, 255, 0.97);
    box-shadow: 0 8px 32px 0 rgba(80, 80, 120, 0.12);
    padding: 2rem 2rem 1.5rem 2rem;
}

@media (max-width: 600px) {
    .login-card-wrapper {
        max-width: 95vw;
        padding: 1rem 0;
    }

    .login-card {
        padding: 1.25rem 0.5rem 1rem 0.5rem;
    }
}
</style>
