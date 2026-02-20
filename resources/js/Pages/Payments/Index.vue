<script setup lang="ts">
import { ref, reactive, onMounted, onUnmounted, watch } from "vue";
import { Head, usePage } from "@inertiajs/vue3";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import flatpickr from "flatpickr";
import "flatpickr/dist/flatpickr.css";
import { showLoader, hideLoader, swalNotification, swalConfirm, formatDate, hasPermission } from "@/mixins/helpers.mixin.js";
import axios from "axios";
import emitter from "@/eventBus";

defineOptions({ layout: AdminLayout });

// ------------------------- STATE -------------------------
const state = reactive({
    payments: [],
    loading: false,
    error: null,
    pagination: {
        current_page: 1,
        last_page: 1,
        per_page: 10,
        total: 0,
        from: 0,
        to: 0,
    },
    searchQuery: '',
    routers: [],
    selectedRouter: null,
    selectedRouterId: 0,
    statusFilter: '',
    dateFrom: '',
    dateTo: '',
    currentUser: null,
    loadingRouters: false,
});

// ------------------------- LOAD PAYMENTS -------------------------
const loadPayments = (page = 1) => {
    if (state.loading) return;
    state.loading = true;

    let url = `/api/transactions?page=${page}`;
    if (state.searchQuery) url += `&search=${encodeURIComponent(state.searchQuery)}`;
    if (state.selectedRouterId) url += `&router_id=${state.selectedRouterId}`;
    if (state.statusFilter) url += `&status=${state.statusFilter}`;
    if (state.dateFrom) url += `&date_from=${state.dateFrom}`;
    if (state.dateTo) url += `&date_to=${state.dateTo}`;

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
            state.error = data.data.length === 0 ? "No payments found." : null;
            state.loading = false;
        })
        .catch(error => {
            state.error = "Failed to load payments.";
            console.error(error);
            state.loading = false;
        });
};

// ------------------------- PAGINATION & SEARCH -------------------------
const handlePageChange = (page: number) => {
    if (page < 1 || page > state.pagination.last_page) return;
    loadPayments(page);
};

const handleSearch = (query: string) => {
    state.searchQuery = query;
    loadPayments(1);
};

// ------------------------- FILTERS -------------------------
const applyFilters = () => {
    loadPayments(1);
};

// ------------------------- LOAD ROUTERS -------------------------
const loadRouters = () => {
    state.loadingRouters = true;
    axios.get('/api/configuration/routers?no_paging=true')
        .then(response => {
            state.routers = response.data;
            if (!state.selectedRouterId && state.routers.length > 0) {
                state.selectedRouterId = state.routers[0].id;
            }
            state.loadingRouters = false;
            loadPayments(1);
        })
        .catch(error => {
            state.loadingRouters = false;
            swalNotification('error', error.response?.data?.message || 'Failed to load routers.');
            console.error("Failed to load routers:", error);
        });
};

// ------------------------- WATCH SELECTED ROUTER -------------------------
watch(() => state.selectedRouterId, (newId) => {
    if (newId || newId === 0) {
        loadPayments(1);
        state.selectedRouter = state.routers.find(router => router.id === newId) || null;
    }
});

// ------------------------- LIFECYCLE -------------------------
onMounted(() => {
    const token = usePage().props.auth.user.api_token;
    if (token) axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
    state.currentUser = usePage().props.auth.user;

    // Initialize flatpickr for date filters
    flatpickr("#dateFrom", { dateFormat: "Y-m-d", onChange: ([selectedDate]) => { state.dateFrom = selectedDate ? selectedDate.toISOString().split('T')[0] : ''; applyFilters(); } });
    flatpickr("#dateTo", { dateFormat: "Y-m-d", onChange: ([selectedDate]) => { state.dateTo = selectedDate ? selectedDate.toISOString().split('T')[0] : ''; applyFilters(); } });
    loadRouters();
    emitter.on('search', handleSearch);
});
onUnmounted(() => {
    emitter.off('search', handleSearch);
});

// ------------------------- TRANSACTION ACTIONS -------------------------
const checkTransactionStatus = async (payment_id: number) => {
    try {
        const result = await swalConfirm.fire({
            icon: 'question',
            html: `
                <p>You are about to check the status of this transaction. Generate a voucher if successful?</p>
                <div class="form-check d-flex gap-3 text-center">
                    <input type="checkbox" class="form-check-input" id="generate-voucher">
                    <label class="form-check-label" for="generate-voucher">Yes Generate Voucher</label>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Check Status',
            cancelButtonText: 'Cancel',
            reverseButtons: true,
            preConfirm: () => {
                const checkbox = document.querySelector('#generate-voucher') as HTMLInputElement;
                return { generateVoucher: checkbox?.checked ?? false };
            },
        });

        if (!result.isConfirmed) return;

        showLoader();
        const { generateVoucher } = result.value;

        const response = await axios.get(`/api/payments/voucher/status/${payment_id}`, {
            params: { generate_voucher: generateVoucher },
        });

        hideLoader();

        const transactionStatus = response.data.transaction?.status;
        const message = response.data.message || 'Status check complete';
        const voucherCode = response.data.voucher?.code;
        const smsMessage = response.data?.sms_sent ? `A notification SMS has been sent` : '';
        const codeMessage = voucherCode ? ` ${smsMessage}<hr/> Voucher Code: ${voucherCode}` : '';

        if (transactionStatus === 'successful') {
            swalNotification('success', `${message}${codeMessage}`).then(() => loadPayments(state.pagination.current_page));
        } else {
            swalNotification('info', message);
        }

    } catch (err) {
        hideLoader();
        swalNotification('error', err.response?.data?.message || 'Error checking transaction status');
    }
};

const generateVoucher = async (payment_id: number) => {
    try {
        const result = await swalConfirm.fire({
            icon: 'question',
            html: `<p>Generate voucher for this transaction?</p>`,
            showCancelButton: true,
            confirmButtonText: 'Generate Voucher',
            cancelButtonText: 'Cancel',
            reverseButtons: true,
        });

        if (!result.isConfirmed) return;

        showLoader("Generating Voucher...");
        const response = await axios.get(`/api/payments/voucher/status/${payment_id}`, { params: { generate_voucher: true } });
        hideLoader();

        if (response.data.transaction?.status === 'successful') {
            const voucherCode = response.data.voucher?.code;
            const smsMessage = response.data?.sms_sent ? `A notification SMS has been sent` : '';
            const codeMessage = voucherCode ? ` ${smsMessage}<hr/> Voucher Code: ${voucherCode}` : '';
            swalNotification('success', `Voucher Generated Successfully: ${codeMessage}`).then(() => loadPayments(state.pagination.current_page));
        } else {
            swalNotification('info', 'Transaction is not successful, cannot generate voucher.');
        }

    } catch (err) {
        hideLoader();
        swalNotification('error', err.response?.data?.message || 'Error generating voucher');
    }
};
</script>

<template>
    <section class="container-fluid">

        <Head title="Payments" />

        <!-- Header & Router Select -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="h3">Payments/Transactions</h4>
            <div class="d-flex align-items-center gap-2">
                <select class="form-select" v-model="state.selectedRouterId"
                    :disabled="state.loading || state.loadingRouters">
                    <option :value="0">All Routers</option>
                    <option v-for="router in state.routers" :key="router.id" :value="router.id">{{ router.name }}
                    </option>
                </select>
                <span v-show="state.loadingRouters" class="spinner-border spinner-border-sm text-secondary"></span>
            </div>
        </div>

        <!-- Filters -->
        <div class="d-flex gap-2 mb-3 align-items-center">
            <select class="form-select w-auto" v-model="state.statusFilter" @change="applyFilters"
                :disabled="state.loading">
                <option value="">All Statuses</option>
                <option value="new">New</option>
                <option value="pending">Pending</option>
                <option value="instructions_sent">Instructions Sent</option>
                <option value="processing_started">Processing Started</option>
                <option value="successful">Successful</option>
                <option value="failed">Failed</option>
            </select>

            <input type="text" id="dateFrom" class="form-control w-auto" placeholder="From Date"
                :disabled="state.loading">
            <input type="text" id="dateTo" class="form-control w-auto" placeholder="To Date"
                :disabled="state.loading">

            <button class="btn btn-secondary" :disabled="state.loading"
                @click="() => { state.statusFilter = ''; state.dateFrom = ''; state.dateTo = ''; applyFilters(); }">
                Reset Filters
            </button>
            <button class="btn btn-primary" @click="loadPayments(1)" :disabled="state.loading">
                <i class="fas fa-sync"></i> Refresh
            </button>
        </div>

        <!-- Payments Table -->
        <div class="card card-body shadow table-responsive position-relative" style="overflow-x: auto; max-width: 100%;">
            <!-- Loading overlay -->
            <div v-if="state.loading || state.loadingRouters"
                class="position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center"
                style="background: rgba(255,255,255,0.65); z-index: 10;">
                <span class="spinner-border text-primary"></span>
            </div>
            <table class="table table-striped" style="min-width: 1200px; width: auto;" v-if="state.payments.length">
                <thead>
                    <tr>
                        <th>Phone No.</th>
                        <th>Package</th>
                        <th>Price (UGX)</th>
                        <th>Charge (UGX)</th>
                        <th>Amount Paid (UGX)</th>
                        <th>Channel</th>
                        <th>Voucher</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="payment in state.payments" :key="payment.id">
                        <td>{{ payment.phone_number }}</td>
                        <td>{{ payment.package?.name || 'WTH' }}</td>
                        <td>{{ payment.package?.formatted_price }}</td>
                        <td>-{{ payment.formatted_charge }}</td>
                        <td>{{ payment.formatted_amount }}</td>
                        <td>
                            <span v-if="payment.channel === 'mobile_money'" class="badge bg-primary">Mobile Money</span>
                            <span v-else-if="payment.channel === 'cash'" class="badge bg-secondary">Cash</span>
                            <span v-else class="text-muted">-</span>
                        </td>
                        <td>{{ payment.voucher?.code }}</td>
                        <td>
                            <span :class="`badge bg-${payment.status === 'successful' ? 'success' : 'danger'}`">{{
                                payment.status }}</span>
                        </td>
                        <td>{{ formatDate(payment.created_at, 'DD MMM YYYY') }}</td>
                        <td>
                            <button class="btn btn-success btn-sm" @click="checkTransactionStatus(payment.payment_id)"
                                v-if="payment.channel === 'mobile_money' && ['new', 'instructions_sent', 'pending', 'processing_started'].includes(payment.status) && hasPermission('check_payment_status', state.currentUser?.permissions_list)">
                                <i class="fas fa-sync"></i> Check Status
                            </button>
                            <button class="btn btn-success btn-sm" @click="generateVoucher(payment.payment_id)"
                                v-else-if="payment.channel === 'mobile_money' && payment.status === 'successful' && !payment.voucher?.code && hasPermission('check_payment_status', state.currentUser?.permissions_list) && payment.amount > 0">
                                <i class="fas fa-print"></i> Generate Voucher
                            </button>
                            <span v-else>
                                <i class="fas fa-ban text-danger"></i>
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div v-if="!state.payments.length && !state.loading && !state.loadingRouters"
                class="text-danger text-center my-3">
                <i class="fas fa-exclamation-triangle"></i> {{ state.error || "No payments found." }}
            </div>
        </div>

        <!-- Pagination -->
        <nav v-if="state.pagination.last_page > 1" class="mt-3">
            <ul class="pagination justify-content-center">
                <li class="page-item" :class="{ disabled: state.pagination.current_page <= 1 }">
                    <a class="page-link" href="#"
                        @click.prevent="handlePageChange(state.pagination.current_page - 1)">Previous</a>
                </li>
                <li class="page-item" v-for="page in state.pagination.last_page" :key="page"
                    :class="{ active: page === state.pagination.current_page }">
                    <a class="page-link" href="#" @click.prevent="handlePageChange(page)">{{ page }}</a>
                </li>
                <li class="page-item"
                    :class="{ disabled: state.pagination.current_page >= state.pagination.last_page }">
                    <a class="page-link" href="#"
                        @click.prevent="handlePageChange(state.pagination.current_page + 1)">Next</a>
                </li>
            </ul>
        </nav>
    </section>
</template>
