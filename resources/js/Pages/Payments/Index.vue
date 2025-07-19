<script setup lang="ts">
import { ref, reactive, onMounted, onUnmounted, watch } from "vue";
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
    routers: [],
    selectedRouter: null,
    selectedRouterId: 1,
});

const loadPayments = (page) => {
    if (state.loading) return;
    state.loading = true;
    let url = `/api/transactions?page=${page}`;
    if (state.searchQuery) {
        url += `&search=${encodeURIComponent(state.searchQuery)}`;
    }
    if (state.selectedRouterId) {
        url += `&router_id=${state.selectedRouterId}`;
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

const handlePageChange = (page: number) => {
    if (page < 1 || page > state.pagination.last_page) return;
    loadPayments(page);
};
const handleSearch = (query: string) => {
    state.searchQuery = query;
    loadPayments(1);
};

const loadRouters = () => {
    axios.get('/api/configuration/routers?no_paging=true')
        .then(response => {
            state.routers = response.data;
        })
        .catch(error => {
            console.error("Failed to load routers:", error);
        });
};

// watch for changes in the selected router
watch(() => state.selectedRouterId, (newId) => {
    if (newId || newId === 0) {
        loadPayments(1);
        // set the selected router based on the ID
        state.selectedRouter = state.routers.find(router => router.id === newId) || null;
    }
});
onMounted(() => {
    const token = usePage().props.auth.user.api_token;

    if (token) {
        axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
    }
    loadPayments(1);
    loadRouters();
    emitter.on('search', handleSearch);
});
onUnmounted(() => {
    emitter.off('search', handleSearch);
});

const checkTransactionStatus = async (payment_id: number) => {
    try {
        const result = await swalConfirm.fire({
            icon: 'question',
            html: `
                <p>You are about to check the status of this transaction. Do you want to generate a voucher if the transaction is successful?</p>
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

        if (transactionStatus === 'successful') {
            const voucherCode = response.data.voucher?.code;
            const smsMessage = response.data?.sms_sent ? `A notification SMS with the voucher code has been sent to the user` : '';
            const codeMessage = voucherCode ? ` ${smsMessage}<hr/> Voucher Code: ${voucherCode}` : '';
            swalNotification('success', `${message}${codeMessage}`);
        } else {
            swalNotification('info', message);
        }

    } catch (err) {
        hideLoader();
        const errorMessage = err.response?.data?.message || 'Error checking transaction status';
        swalNotification('error', errorMessage);
    }
};

</script>

<template>
    <section class="container-fluid">

        <Head title="Payments" />
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="h3">Payments/Transactions</h4>
            <!-- select router-->
            <div class="form-group">
                <select id="routerSelect" class="form-select" v-model="state.selectedRouterId">
                    <option :value="0">All Routers</option>
                    <option v-for="router in state.routers" :key="router.id" :value="router.id">
                        {{ router.name }}
                    </option>
                </select>
            </div>
        </div>

        <!-- Your page content here -->
        <div class="card card-body shadow">
            <table class="table table-striped table-hover" v-if="state.payments.length">
                <thead>
                    <tr>
                        <th>Phone Number</th>
                        <th>Amount</th>
                        <th>Package</th>
                        <th>Voucher</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="payment in state.payments" :key="payment.id">
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
                        <td>
                            <!-- v-if="payment.channel == 'mobile_money'"-->
                            <button class="btn btn-success btn-sm" @click="checkTransactionStatus(payment.payment_id)"
                                v-if="payment.channel == 'mobile_money' && ['new', 'instructions_sent'].includes(payment.status)">
                                <i class="fas fa-sync"></i> Check Status
                            </button>
                            <span v-else>
                                <i class="fas fa-check-circle text-success"></i> No action needed
                            </span>
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