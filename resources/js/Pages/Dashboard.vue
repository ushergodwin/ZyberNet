<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { Head, usePage } from '@inertiajs/vue3';
import { onMounted, reactive } from 'vue';
defineOptions({ layout: AdminLayout });

const state = reactive({
    user: usePage().props.auth.user,
    notifications: usePage().props.notifications || [],
    stats: {
        total_users: 0,
        total_vouchers: 0,
        active_vouchers: 0,
        inactive_vouchers: 0,
        expired_vouchers: 0,
        used_vouchers: 0,
        unused_vouchers: 0,
        total_packages: 0,
        active_routers: 0,
        transactions: 0,
        successful_transactions: 0,
        failed_transactions: 0,
        total_revenue: 0
    },
    isLoading: false
});

// get dashboard stats
const getDashboardStats = async () => {
    try {
        state.isLoading = true;
        const response = await axios.get('/api/reports/stats');
        Object.assign(state.stats, response.data);
        state.isLoading = false;
    } catch (error) {
        console.error('Failed to fetch dashboard stats:', error);
    }
};
onMounted(() => {
    const token = usePage().props.auth.user.api_token;

    if (token) {
        axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
    }
    getDashboardStats();
});

const capitalize = (str) => {
    // first replace underscores with spaces
    str = str.replace(/_/g, ' ');
    // then capitalize the first letter of each word
    return str.replace(/\b\w/g, char => char.toUpperCase());
};
</script>

<template>
    <section class="container-fluid">

        <Head title="Dashboard" />
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3">Dashboard</h1>
        </div>
        <div class="row">
            <div class="col-md-3 mb-3" v-for="key in Object.keys(state.stats)" :key="key">
                <div class="card bg-light text-dark p-3">
                    <h5>{{ capitalize(key) }}</h5>
                    <span class="spinner-border spinner-border-sm text-primary" v-if="state.isLoading"></span>
                    <p class="h2" v-else>{{ state.stats[key] }}</p>
                </div>
            </div>
        </div>

    </section>
</template>
