<script setup lang="ts">
import { ref, reactive, onMounted, onUnmounted } from "vue";
import { Head, usePage } from "@inertiajs/vue3";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import { showLoader, hideLoader, swalNotification, swalConfirm } from "@/mixins/helpers.mixin.js";
import axios from "axios";
import emitter from "@/eventBus";
defineOptions({ layout: AdminLayout });
onMounted(() => {
    const token = usePage().props.auth.user.api_token;

    if (token) {
        axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
    }
});

const state = reactive({
    vouchers: [],
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
    voucherTransaction: null,
    showTransactionModal: false,
    selectedVoucher: null,
});

const loadVouchers = (page) => {
    if (state.loading) return;
    state.loading = true;
    let url = `/api/vouchers?page=${page}`;
    if (state.searchQuery) {
        url += `&search=${encodeURIComponent(state.searchQuery)}`;
    }
    axios.get(url)
        .then(response => {
            const data = response.data;
            state.vouchers = data.data;
            state.pagination = {
                current_page: data.current_page,
                last_page: data.last_page,
                per_page: data.per_page,
                total: data.total,
                from: data.from,
                to: data.to
            };
            if (data.data.length === 0) {
                state.error = "No more vouchers available.";
            }
            state.loading = false;
        })
        .catch(error => {
            state.error = "Failed to load vouchers.";
            console.error(error);
            state.loading = false;
        });
};

const deleteVoucher = (id) => {
    swalConfirm.fire({
        title: "Delete Voucher",
        text: "You are about to delete this voucher. Are you sure?",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Yes, delete it!",
        cancelButtonText: "No, cancel!",
        reverseButtons: true,
    })
        .then((result) => {
            if (result.isConfirmed) {
                axios.delete(`/api/vouchers/${id}`)
                    .then(() => {
                        state.vouchers = state.vouchers.filter(voucher => voucher.id !== id);
                        swalNotification("success", "Voucher deleted successfully",);
                    })
                    .catch(error => {
                        swalNotification("error", "Failed to delete voucher",);
                        console.error(error);
                    });
            }
        });
};
// get voucher transaction details
const getVoucherTransaction = (voucher) => {
    state.voucherTransaction = null;
    state.showTransactionModal = false;
    state.selectedVoucher = voucher;
    showLoader();
    axios.get(`/api/vouchers/${voucher.id}/transaction`)
        .then(response => {
            hideLoader();
            state.voucherTransaction = response.data;
            state.showTransactionModal = true;
        })
        .catch(error => {
            hideLoader();
            console.error("Failed to load voucher transaction details:", error);
            swalNotification("error", "Failed to load voucher transaction details");
        });
};
const handleSearch = (query) => {
    state.searchQuery = query;
    loadVouchers(1);
};
const handlePageChange = (page) => {
    if (page < 1 || page > state.pagination.last_page) return;
    loadVouchers(page);
};
onMounted(() => {
    loadVouchers(1);
    emitter.on('search', handleSearch);
});
onUnmounted(() => {
    emitter.off('search', handleSearch);
});
</script>

<template>
    <section class="container-fluid">

        <Head title="Vouchers" />
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="h3">Vouchers</h4>
            <!-- <button class="btn btn-primary btn-sm" @click="$inertia.visit('/vouchers/create')">
                <i class="fas fa-plus"></i> Create Voucher
            </button> -->
        </div>

        <!-- Your page content here -->
        <div class="card card-body shadow">
            <table class="table table-striped table-hover" v-if="state.vouchers.length">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Package</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Is Used</th>
                        <td>Expiry</td>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="voucher in state.vouchers" :key="voucher.id">
                        <td>{{ voucher.code }}</td>
                        <td>{{ voucher.package.name }}</td>
                        <td>{{ voucher.package.formatted_price }}</td>
                        <td>{{ voucher.is_active ? `Active` : `Expired` }}</td>
                        <td>{{ voucher.used_at ? `Yes` : `No` }}</td>
                        <td>{{ voucher.expires_at ? voucher.formatted_expiry_date : `N/A` }}</td>
                        <td>
                            <div class="d-flex gap-3">
                                <a href="#" class="text-primary" @click="getVoucherTransaction(voucher)">
                                    <i class="fas fa-credit-card text-primary"></i>
                                </a>
                                <!-- <a href="#" class="text-danger" @click="deleteVoucher(voucher.id)">
                                    <i class="fas fa-trash text-danger"></i>
                                </a> -->
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Loading and error handling -->
            <div v-if="state.loading" class="text-center my-3">
                <i class="fas fa-spinner fa-spin"></i> Loading...
            </div>
            <div v-if="!state.vouchers.length" class="text-danger text-center my-3">
                <i class="fas fa-exclamation-triangle"></i> {{ state.error || "No vouchers found." }}
            </div>
            <!-- build pages -->
            <div class="d-flex justify-content-end mt-3 gap-2" v-if="state.vouchers.length">
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

            <!-- Transaction Modal -->
            <div v-if="state.showTransactionModal" class="modal fade show d-block" tabindex="-1"
                style="background:rgba(0,0,0,0.3)">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Transaction Details #{{ state.selectedVoucher?.code }}</h5>
                            <button type="button" class="btn-close"
                                @click="state.showTransactionModal = false"></button>
                        </div>
                        <div class="alert alert-danger" v-if="!state.voucherTransaction">
                            No transaction details available.
                        </div>
                        <table class="table table-striped table-hover" v-else>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Phone Number</th>
                                    <th>Amount</th>
                                    <th>Package</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>{{ state.voucherTransaction?.payment_id }}</td>
                                    <td>{{ state.voucherTransaction?.phone_number }}</td>
                                    <td>{{ state.voucherTransaction?.formatted_amount }}</td>
                                    <td>{{ state.voucherTransaction?.package.name }}</td>
                                    <td>
                                        <span
                                            :class="`badge bg-${state.voucherTransaction?.status === 'successful' ? 'success' : 'danger'}`">
                                            {{ state.voucherTransaction?.status }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>