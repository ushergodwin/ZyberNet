<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { Head, usePage } from '@inertiajs/vue3';
import { onMounted, reactive, watch } from 'vue';
defineOptions({ layout: AdminLayout });

const state = reactive({
    user: usePage().props.auth.user,
    notifications: usePage().props.notifications || [],
    stats: {
        total_revenue: 0,
        inactive_users: 0,
        online_users: 0,
        users_created_today: 0,
        most_used_profile: "Unknown",
        top_profiles_by_user_count: null,
        total_vouchers: 0,
        expired_vouchers: 0,
        total_packages: 0,
        active_routers: 0,
        transactions: 0,
        successful_transactions: 0,
        failed_transactions: 0,
    },
    isLoading: false,
    routers: [],
    selectedRouter: null,
    selectedRouterId: 1
});

// get dashboard stats
const getDashboardStats = async () => {
    try {
        let url = '/api/reports/stats';
        if (state.selectedRouterId) {
            url += `?router_id=${state.selectedRouterId}`;
        }
        state.isLoading = true;
        const response = await axios.get(url);
        Object.assign(state.stats, response.data);
        state.isLoading = false;
    } catch (error) {
        console.error('Failed to fetch dashboard stats:', error);
    }
};

// load routers
const loadRouters = async () => {
    try {
        const response = await axios.get('/api/configuration/routers?no_paging=true');
        state.routers = response.data;
    } catch (error) {
        console.error('Failed to load routers:', error);
    }
};

// watch for selectedRouterId changes
watch(() => state.selectedRouterId, (newId) => {
    state.selectedRouter = state.routers.find(router => router.id === newId);
    if (newId || newId === 0) {
        getDashboardStats();
    }
});
onMounted(() => {
    const token = usePage().props.auth.user.api_token;

    if (token) {
        axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
    }
    getDashboardStats();
    loadRouters();
});

const capitalize = (str) => {
    if (!str) return '';
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
            <!-- Router Selector -->
            <select v-model="state.selectedRouterId" class="form-select w-auto">
                <option :value="0">All Routers</option>
                <option v-for="router in state.routers" :key="router.id" :value="router.id">
                    {{ router.name }}
                </option>
            </select>
        </div>
        <div class="row">
            <div class="col-md-3 mb-3" v-for="key in Object.keys(state.stats)" :key="key">
                <div class="card bg-light text-dark p-3"
                    v-if="key !== 'top_profiles_by_user_count' && state.stats[key] !== undefined">
                    <h5>{{ capitalize(key) }}</h5>
                    <span class="spinner-border spinner-border-sm text-primary" v-if="state.isLoading"></span>
                    <p class="h2" v-else>{{ state.stats[key] }}</p>
                </div>
            </div>
        </div>

        <div v-if="state.stats.top_profiles_by_user_count && Object.keys(state.stats.top_profiles_by_user_count).length"
            class="mt-4">
            <h5>Top Profiles by User Count</h5>
            <table class="table table-sm table-bordered">
                <thead>
                    <tr>
                        <th>Profile</th>
                        <th>User Count</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(count, profile) in state.stats.top_profiles_by_user_count" :key="profile">
                        <td>{{ profile }}</td>
                        <td>{{ count }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</template>
