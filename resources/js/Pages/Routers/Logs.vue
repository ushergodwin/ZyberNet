<script setup lang="ts">
import { ref, reactive, onMounted, onUnmounted, watch } from "vue";
import { Head, usePage } from "@inertiajs/vue3";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import { showLoader, hideLoader, swalNotification, swalConfirm, formatDate } from "@/mixins/helpers.mixin.js";
import axios from "axios";
import emitter from "@/eventBus";
defineOptions({ layout: AdminLayout });

const state = reactive({
    routerLogs: [],
    loading: false,
    error: null,
    pagination: {
        current_page: 0,
        last_page: 0,
        per_page: 10,
        total: 0,
        from: 0,
        to: 0,
    },
    searchQuery: '',
    routers: [],
    selectedRouter: null,
    selectedRouterId: null,
    loadingRouters: false,
});

const loadRouterLogs = (page) => {
    if (state.loading) return;
    state.loading = true;
    let url = `/api/configuration/router-logs?page=${page}`;
    if (state.searchQuery) {
        url += `&search=${encodeURIComponent(state.searchQuery)}`;
    }
    if (state.selectedRouterId) {
        url += `&router_id=${state.selectedRouterId}`;
    }

    axios.get(url)
        .then(response => {
            const data = response.data;
            state.routerLogs = data.data;
            state.pagination = {
                current_page: data.current_page,
                last_page: data.last_page,
                per_page: data.per_page,
                total: data.total,
                from: data.from,
                to: data.to
            };

            if (data.data.length === 0) {
                state.error = "No more router logs available.";
            }
            state.loading = false;
        })
        .catch(error => {
            state.error = "Failed to load router logs.";
            console.error(error);
            state.loading = false;
        });
};
const handleSearch = (query) => {
    state.searchQuery = query;
    loadRouterLogs(1);
};
const handlePageChange = (page) => {
    if (page < 1 || page > state.pagination.last_page) return;
    loadRouterLogs(page);
};

// load routers
const loadRouters = async () => {
    try {
        state.loadingRouters = true;
        const response = await axios.get('/api/configuration/routers?no_paging=true');
        state.routers = response.data;
        if (!state.selectedRouterId && state.routers.length > 0) {
            state.selectedRouterId = state.routers[0].id;
        }
        state.loadingRouters = false;
    } catch (error) {
        console.error('Failed to load routers:', error);
    }
};

// watch for selectedRouterId changes
watch(() => state.selectedRouterId, (newId) => {
    state.selectedRouter = state.routers.find(router => router.id === newId);
    if (newId || newId === 0) {
        loadRouterLogs(1);
    }
});
onMounted(() => {
    const token = usePage().props.auth.user.api_token;

    if (token) {
        axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
    }
    loadRouters()
        .then(() => loadRouterLogs(1));
    emitter.on('search', handleSearch);
});
onUnmounted(() => {
    emitter.off('search', handleSearch);
});
</script>

<template>
    <section class="container-fluid">

        <Head title="Router Logs" />
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="h3">Router Logs</h4>
            <!-- Router selection -->
            <div class="d-flex align-items-center gap-2">
                <select class="form-select w-auto" v-model="state.selectedRouterId"
                    :disabled="state.loading || state.loadingRouters">
                    <option :value="0">All Routers</option>
                    <option v-for="router in state.routers" :key="router.id" :value="router.id">
                        {{ router.name }}
                    </option>
                </select>
                <span v-show="state.loadingRouters" class="spinner-border spinner-border-sm text-secondary"></span>
            </div>
        </div>
        <!-- Your page content here -->
        <div class="card card-body shadow table-responsive position-relative" style="overflow-x: auto; max-width: 100%;">
            <!-- Loading overlay -->
            <div v-show="state.loading || state.loadingRouters"
                class="position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center"
                style="background: rgba(255,255,255,0.65); z-index: 10;">
                <span class="spinner-border text-primary"></span>
            </div>
            <table class="table table-striped" style="min-width: 1200px; width: auto;" v-if="state.routerLogs.length">
                <thead>
                    <tr>
                        <th>Action</th>
                        <th>Success</th>
                        <th>Message</th>
                        <th>Logged At</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="log in state.routerLogs" :key="log.id">
                        <td>{{ log.action }}</td>
                        <td>
                            <span class="badge" :class="log.success ? 'bg-success' : 'bg-danger'">{{ log.success ? 'Yes'
                                : 'No' }}</span>
                        </td>
                        <td>{{ log.message }}</td>
                        <td>{{ formatDate(log.created_at, 'DD MMM YYYY') }}</td>
                    </tr>
                </tbody>
            </table>
            <div v-if="!state.routerLogs.length && !state.loading && !state.loadingRouters"
                class="text-danger text-center my-3">
                <i class="fas fa-exclamation-triangle"></i> {{ state.error || "No router logs found." }}
            </div>
            <!-- build pages -->
            <div class="d-flex justify-content-end mt-3 gap-2" v-if="state.routerLogs.length">
                <nav>
                    <ul class="pagination">
                        <!-- Previous page -->
                        <li class="page-item" :class="{ disabled: state.pagination.current_page === 1 }">
                            <a href="#" class="page-link"
                                @click.prevent="handlePageChange(state.pagination.current_page - 1)">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>

                        <!-- Page numbers -->
                        <li v-for="page in state.pagination.last_page" :key="page" class="page-item"
                            :class="{ active: page === state.pagination.current_page }">
                            <a href="#" class="page-link" @click.prevent="handlePageChange(page)">
                                {{ page }}
                            </a>
                        </li>

                        <!-- Next page -->
                        <li class="page-item"
                            :class="{ disabled: state.pagination.current_page === state.pagination.last_page }">
                            <a href="#" class="page-link"
                                @click.prevent="handlePageChange(state.pagination.current_page + 1)">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>

    </section>
</template>