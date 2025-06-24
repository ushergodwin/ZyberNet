<script setup lang="ts">
import { ref, reactive, onMounted, onUnmounted } from "vue";
import { Head, usePage } from "@inertiajs/vue3";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import { showLoader, hideLoader, swalNotification, swalConfirm, formatDate } from "@/mixins/helpers.mixin.js";
import axios from "axios";
import emitter from "@/eventBus";

defineOptions({ layout: AdminLayout });


const state = reactive({
    payments: [],
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
});

const loadPayments = (page) => {
    if (state.loading) return;
    state.loading = true;
    let url = `/api/transactions?page=${page}`;
    if (state.searchQuery) {
        url += `&search=${encodeURIComponent(state.searchQuery)}`;
    }
    axios.get(url)
        .then(response => {
            const data = response.data;
            state.payments = data.data;
            state.pagination = {
                current_page: data.current_page,
                last_page: data.last_page,
                per_page: data.per_page,
                total: data.total,
                from: data.from,
                to: data.to
            };

            if (data.data.length === 0) {
                state.error = "No more payments available.";
            }
            state.loading = false;
        })
        .catch(error => {
            state.error = "Failed to load payments.";
            console.error(error);
            state.loading = false;
        });
};

const handlePageChange = (page) => {
    if (page < 1 || page > state.pagination.last_page) return;
    loadPayments(page);
};
const handleSearch = (query: string) => {
    state.searchQuery = query;
    loadPayments(1);
};
onMounted(() => {
    const token = usePage().props.auth.user.api_token;

    if (token) {
        axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
    }
    loadPayments(1);
    emitter.on('search', handleSearch);
});
onUnmounted(() => {
    emitter.off('search', handleSearch);
});
</script>

<template>
    <section class="container-fluid">

        <Head title="Payments" />
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="h3">Payments/Transactions</h4>
        </div>

        <!-- Your page content here -->
        <div class="card card-body shadow">
            <table class="table table-striped table-hover" v-if="state.payments.length">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Phone Number</th>
                        <th>Amount</th>
                        <th>Package</th>
                        <th>Voucher</th>
                        <th>Status</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="payment in state.payments" :key="payment.id">
                        <td>{{ payment.payment_id }}</td>
                        <td>{{ payment.phone_number }}</td>
                        <td>{{ payment.formatted_amount }}</td>
                        <td>{{ payment.package.name }}</td>
                        <td>{{ payment.voucher?.code }}</td>
                        <td>
                            <span :class="`badge bg-${payment.status === 'successful' ? 'success' : 'danger'}`">
                                {{ payment.status }}
                            </span>
                        </td>

                        <td>
                            {{ formatDate(payment.created_at) }}
                        </td>
                    </tr>
                </tbody>
            </table>
            <!-- Loading and error handling -->
            <div v-if="state.loading" class="text-center my-3">
                <i class="fas fa-spinner fa-spin"></i> Loading...
            </div>
            <div v-if="!state.payments.length" class="text-danger text-center my-3">
                <i class="fas fa-exclamation-triangle"></i> {{ state.error || "No payments found." }}
            </div>
            <!-- build pages -->
            <div class="d-flex justify-content-end mt-3 gap-2" v-if="state.payments.length">
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