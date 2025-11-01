<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { Head, usePage } from '@inertiajs/vue3';
import { onMounted, reactive, watch } from 'vue';
import flatpickr from 'flatpickr';
import 'flatpickr/dist/flatpickr.css';
import axios from 'axios';

defineOptions({ layout: AdminLayout });

const state = reactive({
    user: usePage().props.auth.user,
    stats: {},
    isLoading: false,
    routers: [],
    selectedRouterId: 1,
    dateFrom: '',
    dateTo: '',
    allTime: false, // new toggle for all-time stats
});

// ----------------- LOADERS -----------------
const getDashboardStats = async () => {
    try {
        state.isLoading = true;
        let url = '/api/reports/stats';
        const params = [];

        if (state.selectedRouterId && state.selectedRouterId !== 0) {
            params.push(`router_id=${state.selectedRouterId}`);
        }
        if (!state.allTime) {
            if (state.dateFrom) params.push(`date_from=${state.dateFrom}`);
            if (state.dateTo) params.push(`date_to=${state.dateTo}`);
        } else {
            params.push('all=true'); // tell API to ignore date filters
        }

        if (params.length > 0) url += `?${params.join('&')}`;

        const response = await axios.get(url);
        state.stats = response.data;
        setTimeout(() => state.isLoading = false, 1000);
    } catch (error) {
        console.error('Failed to fetch dashboard stats:', error);
    }
};

// ----------------- LOADERS -----------------
const loadRouters = async () => {
    try {
        const response = await axios.get('/api/configuration/routers?no_paging=true');
        state.routers = response.data;
        if (!state.selectedRouterId && state.routers.length > 0) {
            state.selectedRouterId = state.routers[0].id;
        }
    } catch (error) {
        console.error('Failed to load routers:', error);
    }
};

// ----------------- WATCHERS -----------------
watch(() => state.selectedRouterId, () => getDashboardStats());
watch(() => [state.dateFrom, state.dateTo, state.allTime], () => getDashboardStats());

// ----------------- INIT -----------------
onMounted(() => {
    const token = usePage().props.auth.user.api_token;
    if (token) axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;

    // flatpickr date range pickers
    flatpickr("#dateFrom", {
        dateFormat: "Y-m-d",
        onChange: ([date]) => { state.dateFrom = date ? date.toISOString().split('T')[0] : ''; },
    });
    flatpickr("#dateTo", {
        dateFormat: "Y-m-d",
        onChange: ([date]) => { state.dateTo = date ? date.toISOString().split('T')[0] : ''; },
    });

    getDashboardStats();
    loadRouters();
});

// ----------------- HELPERS -----------------
const capitalize = (str) => {
    if (!str) return '';
    str = str.replace(/_/g, ' ');
    return str.replace(/\b\w/g, char => char.toUpperCase());
};

const resetFilters = () => {
    state.dateFrom = '';
    state.dateTo = '';
    state.allTime = false;
    getDashboardStats();
};
</script>

<template>
    <section class="container-fluid">

        <Head title="Dashboard" />

        <!-- Header -->
        <h1 class="h3 mb-1">Dashboard Statistics</h1>

        <!-- Filters -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="d-flex gap-2 align-items-center">
                <select v-model="state.selectedRouterId" class="form-select w-auto">
                    <option :value="0">All Routers</option>
                    <option v-for="router in state.routers" :key="router.id" :value="router.id">
                        {{ router.name }}
                    </option>
                </select>

                <input type="text" id="dateFrom" class="form-control w-auto" placeholder="From Date"
                    :disabled="state.allTime" />
                <input type="text" id="dateTo" class="form-control w-auto" placeholder="To Date"
                    :disabled="state.allTime" />

                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="allTimeCheck" v-model="state.allTime">
                    <label class="form-check-label" for="allTimeCheck">All Time</label>
                </div>

                <button class="btn btn-secondary btn-sm" @click="resetFilters">
                    Reset
                </button>
            </div>
        </div>

        <!-- Stats Cards -->
        <div v-if="!state.isLoading" class="row">
            <div class="col-md-3 mb-3" v-for="key in Object.keys(state.stats)" :key="key">
                <div class="card bg-light text-dark p-3"
                    v-if="key !== 'top_profiles_by_user_count' && state.stats[key] !== undefined">
                    <h5>{{ capitalize(key) }}</h5>
                    <p class="h4 mb-0">{{ state.stats[key] }}</p>
                </div>
            </div>
        </div>

        <div v-else class="alert alert-info">
            <span class="spinner-border spinner-border-sm text-primary me-2"></span> Loading dashboard stats...
        </div>

        <!-- Top Profiles -->
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
