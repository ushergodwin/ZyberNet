<script setup lang="ts">
import { ref, reactive, onMounted, onUnmounted, computed } from "vue";
import { usePage } from "@inertiajs/vue3";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import { swalNotification, hasPermission, showLoader, hideLoader } from "@/mixins/helpers.mixin.js";
import Swal from "sweetalert2";
import axios from "axios";

defineOptions({ layout: AdminLayout });

const statusLabels: Record<string, string> = {
    new: 'Payment initiated...',
    pending: 'Waiting for confirmation...',
    instructions_sent: 'Please enter your PIN on your phone...',
    processing_started: 'Processing your payment...',
    successful: 'Payment successful!',
    failed: 'Payment failed.',
};

const state = reactive({
    gatewayInfo: null as any,
    gatewayLoading: false,

    paymentForm: {
        phone_number: '',
        amount: 5000,
    },
    paymentLoading: false,

    voucherForm: {
        phone_number: '',
        router_id: '',
        package_id: '',
    },
    voucherLoading: false,

    routers: [] as any[],
    routersLoading: false,

    packages: [] as any[],
    packagesLoading: false,

    testResults: [] as any[],

    currentUser: null as any,
});

let activePollTimer: ReturnType<typeof setInterval> | null = null;

const canTestPayments = computed(() => {
    return hasPermission('test_payments', state.currentUser?.permissions_list);
});

const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-UG', {
        style: 'currency',
        currency: 'UGX',
        minimumFractionDigits: 0
    }).format(amount);
};

const formatDate = (date: string) => {
    return new Date(date).toLocaleString('en-UG', {
        dateStyle: 'short',
        timeStyle: 'short'
    });
};

const getStatusBadgeClass = (status: string) => {
    const classes: Record<string, string> = {
        'successful': 'bg-success',
        'failed': 'bg-danger',
        'pending': 'bg-warning text-dark',
        'instructions_sent': 'bg-info',
        'processing_started': 'bg-primary',
        'new': 'bg-secondary',
    };
    return classes[status] || 'bg-secondary';
};

function stopPolling() {
    if (activePollTimer) {
        clearInterval(activePollTimer);
        activePollTimer = null;
    }
}

function updateSwalContent(text: string) {
    const content = Swal.getHtmlContainer();
    if (content) {
        content.textContent = text;
    }
}

// Load gateway info
const loadGatewayInfo = async () => {
    state.gatewayLoading = true;
    try {
        const response = await axios.get('/api/admin/test/gateway-info');
        state.gatewayInfo = response.data;
    } catch (error: any) {
        console.error('Failed to load gateway info:', error);
        swalNotification('error', error.response?.data?.message || 'Failed to load gateway info');
    } finally {
        state.gatewayLoading = false;
    }
};

// Load routers the user has access to
const loadRouters = async () => {
    state.routersLoading = true;
    try {
        const response = await axios.get('/api/configuration/routers', { params: { no_paging: true } });
        state.routers = response.data;
    } catch (error) {
        console.error('Failed to load routers:', error);
    } finally {
        state.routersLoading = false;
    }
};

// Load voucher packages for a specific router
const loadPackages = async (routerId: string | number) => {
    state.packagesLoading = true;
    state.packages = [];
    state.voucherForm.package_id = '';
    try {
        const response = await axios.get('/api/configuration/vouchers/packages', { params: { router_id: routerId } });
        const packages = response.data.packages || response.data;
        state.packages = (Array.isArray(packages) ? packages : []).filter((p: any) => p.is_active);
    } catch (error) {
        console.error('Failed to load packages:', error);
    } finally {
        state.packagesLoading = false;
    }
};

// When router changes, reload packages
const onRouterChange = (routerId: string | number) => {
    if (routerId) {
        loadPackages(routerId);
    } else {
        state.packages = [];
        state.voucherForm.package_id = '';
    }
};

/**
 * Poll payment status using SweetAlert for live feedback.
 */
function pollWithSwal(reference: string, type: 'payment' | 'voucher', resultIndex: number) {
    const pollingInterval = 5000;
    const timeoutDuration = 180000; // 3 minutes
    let elapsed = 0;

    // Show the loading Swal
    Swal.fire({
        title: 'Payment prompt sent',
        html: 'Please enter your PIN on your phone to confirm the transaction...',
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: () => {
            Swal.showLoading();
        },
    });

    async function pollOnce() {
        elapsed += pollingInterval;

        try {
            const response = await axios.get(`/api/admin/test/payment/status/${reference}`);
            const txnStatus = response.data.transaction?.status || response.data.status;
            const voucher = response.data.voucher;

            // Update the history entry
            state.testResults[resultIndex].status = txnStatus;
            if (response.data.transaction?.mfscode) {
                state.testResults[resultIndex].mfscode = response.data.transaction.mfscode;
            }

            // Update swal text with current status
            updateSwalContent(statusLabels[txnStatus] || 'Processing...');

            if (txnStatus === 'successful') {
                stopPolling();

                if (type === 'voucher' && voucher) {
                    state.testResults[resultIndex].voucher = voucher;
                    Swal.fire({
                        icon: 'success',
                        title: 'Payment Successful!',
                        html: `
                            <p>Transaction completed successfully.</p>
                            <div style="background: #f0f9f0; border-radius: 8px; padding: 15px; margin-top: 10px;">
                                <strong>Voucher Code:</strong>
                                <div style="font-size: 1.5em; font-weight: bold; margin: 8px 0; letter-spacing: 2px;">${voucher.code}</div>
                                <small>Expires: ${new Date(voucher.expires_at).toLocaleString()}</small>
                            </div>
                        `,
                    });
                } else {
                    Swal.fire({
                        icon: 'success',
                        title: 'Payment Successful!',
                        html: `<p>Transaction completed successfully.</p>
                               <p><small>MFS Code: ${response.data.transaction?.mfscode || 'N/A'}</small></p>`,
                    });
                }
                return;
            }

            if (txnStatus === 'failed') {
                stopPolling();
                Swal.fire({
                    icon: 'error',
                    title: 'Payment Failed',
                    html: 'The transaction could not be completed. Please try again.',
                });
                return;
            }

            if (elapsed >= timeoutDuration) {
                stopPolling();
                state.testResults[resultIndex].status = 'timed_out';
                Swal.fire({
                    icon: 'warning',
                    title: 'Payment Timed Out',
                    html: 'The payment is taking too long. Please contact IT support for assistance. Your payment may still be processing.',
                });
            }
        } catch (err) {
            if (elapsed >= timeoutDuration) {
                stopPolling();
                state.testResults[resultIndex].status = 'timed_out';
                Swal.fire({
                    icon: 'warning',
                    title: 'Payment Timed Out',
                    html: 'Could not verify payment status. Please contact IT support.',
                });
            }
        }
    }

    pollOnce(); // Check immediately
    activePollTimer = setInterval(pollOnce, pollingInterval);
}

// Test payment (no voucher)
const testPayment = async () => {
    if (!state.paymentForm.phone_number || !state.paymentForm.amount) {
        swalNotification('error', 'Please fill in all required fields');
        return;
    }

    state.paymentLoading = true;

    try {
        const response = await axios.post('/api/admin/test/payment', state.paymentForm);

        if (!response.data.success) {
            swalNotification('error', response.data.message || 'Payment test failed');
            return;
        }

        const reference = response.data.payment_id || response.data.transaction_id;

        // Add to history
        state.testResults.unshift({
            type: 'payment',
            timestamp: new Date().toISOString(),
            phone_number: state.paymentForm.phone_number,
            amount: state.paymentForm.amount,
            gateway: response.data.gateway,
            status: response.data.status || 'pending',
            payment_id: response.data.payment_id,
            transaction_id: response.data.transaction_id,
            mfscode: null,
        });

        // Start SweetAlert polling
        pollWithSwal(reference, 'payment', 0);

    } catch (error: any) {
        console.error('Test payment error:', error);
        swalNotification('error', error.response?.data?.message || 'Failed to initiate test payment');
    } finally {
        state.paymentLoading = false;
    }
};

// Test voucher purchase
const testVoucherPurchase = async () => {
    if (!state.voucherForm.phone_number || !state.voucherForm.package_id) {
        swalNotification('error', 'Please fill in all required fields');
        return;
    }

    state.voucherLoading = true;

    try {
        const response = await axios.post('/api/admin/test/voucher-purchase', state.voucherForm);

        if (!response.data.success) {
            swalNotification('error', response.data.message || 'Voucher purchase test failed');
            return;
        }

        const selectedPackage = state.packages.find(p => p.id == state.voucherForm.package_id);
        const paymentData = response.data.payment_data || {};
        const reference = paymentData.id || response.data.transaction_id;

        // Add to history
        state.testResults.unshift({
            type: 'voucher',
            timestamp: new Date().toISOString(),
            phone_number: state.voucherForm.phone_number,
            package_name: selectedPackage?.name || 'Unknown',
            amount: selectedPackage?.price || 0,
            gateway: response.data.gateway || paymentData._gateway,
            status: paymentData.status || 'pending',
            payment_id: paymentData.id,
            transaction_id: response.data.transaction_id,
            mfscode: null,
            voucher: null,
        });

        // Start SweetAlert polling
        pollWithSwal(reference, 'voucher', 0);

    } catch (error: any) {
        console.error('Voucher purchase test error:', error);
        swalNotification('error', error.response?.data?.message || 'Failed to initiate voucher purchase test');
    } finally {
        state.voucherLoading = false;
    }
};

// Manual re-check for a result in the history
const recheckStatus = async (result: any, index: number) => {
    const reference = result.payment_id || result.transaction_id;
    if (!reference) {
        swalNotification('error', 'No payment reference found');
        return;
    }

    if (result.status === 'successful' || result.status === 'failed') {
        swalNotification('info', `Payment already ${result.status}.`);
        return;
    }

    pollWithSwal(reference, result.type, index);
};

onMounted(() => {
    const token = usePage().props.auth?.user?.api_token;
    if (token) {
        axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
    }
    state.currentUser = usePage().props.auth?.user;

    if (canTestPayments.value) {
        loadGatewayInfo();
        loadRouters();
    }
});

onUnmounted(() => {
    stopPolling();
});
</script>

<template>
    <section class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-1">Payment Gateway Testing</h4>
                <p class="text-muted mb-0">Test payment gateway connectivity and voucher purchase flow</p>
            </div>
            <button class="btn btn-outline-secondary" @click="loadGatewayInfo" :disabled="state.gatewayLoading">
                <i class="fas fa-sync-alt me-2" :class="{ 'fa-spin': state.gatewayLoading }"></i>
                Refresh Gateway Info
            </button>
        </div>

        <!-- Permission check -->
        <div v-if="!canTestPayments" class="alert alert-warning">
            <i class="fas fa-exclamation-triangle me-2"></i>
            You don't have permission to test payments. Please contact your administrator.
        </div>

        <template v-else>
            <!-- Gateway Info Card -->
            <div class="card mb-4 shadow-sm">
                <div class="card-header bg-primary text-white">
                    <i class="fas fa-info-circle me-2"></i> Active Gateway Configuration
                </div>
                <div class="card-body">
                    <div v-if="state.gatewayLoading" class="text-center py-3">
                        <i class="fas fa-spinner fa-spin"></i> Loading gateway info...
                    </div>
                    <div v-else-if="state.gatewayInfo" class="row">
                        <div class="col-md-3">
                            <strong>Active Gateway:</strong>
                            <span class="badge bg-success ms-2 text-uppercase">{{ state.gatewayInfo.active_gateway }}</span>
                        </div>
                        <div class="col-md-3">
                            <strong>Configured:</strong>
                            <span class="ms-2">{{ state.gatewayInfo.configured_gateway }}</span>
                        </div>
                        <div class="col-md-3">
                            <strong>YoPayments:</strong>
                            <span :class="state.gatewayInfo.yopayments_configured ? 'text-success' : 'text-danger'" class="ms-2">
                                <i :class="state.gatewayInfo.yopayments_configured ? 'fas fa-check-circle' : 'fas fa-times-circle'"></i>
                                {{ state.gatewayInfo.yopayments_configured ? 'Configured' : 'Not configured' }}
                            </span>
                        </div>
                        <div class="col-md-3">
                            <strong>CinemaUG:</strong>
                            <span :class="state.gatewayInfo.cinemaug_configured ? 'text-success' : 'text-danger'" class="ms-2">
                                <i :class="state.gatewayInfo.cinemaug_configured ? 'fas fa-check-circle' : 'fas fa-times-circle'"></i>
                                {{ state.gatewayInfo.cinemaug_configured ? 'Configured' : 'Not configured' }}
                            </span>
                        </div>
                        <div class="col-12 mt-2" v-if="state.gatewayInfo.auto_switch_enabled">
                            <span class="badge bg-info">
                                <i class="fas fa-exchange-alt me-1"></i>
                                Auto-switch enabled (every {{ state.gatewayInfo.auto_switch_every }} transactions)
                            </span>
                        </div>
                    </div>
                    <div v-else class="text-muted">
                        <i class="fas fa-exclamation-circle me-2"></i> Gateway info not available
                    </div>
                </div>
            </div>

            <!-- Test Forms -->
            <div class="row">
                <!-- Test Payment Form (No Voucher) -->
                <div class="col-md-6 mb-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-header bg-info text-white">
                            <i class="fas fa-flask me-2"></i> Test Payment (No Voucher)
                        </div>
                        <div class="card-body">
                            <p class="text-muted small mb-3">
                                Test gateway connectivity by initiating a real payment without creating a voucher.
                            </p>
                            <form @submit.prevent="testPayment">
                                <div class="mb-3">
                                    <label class="form-label">Phone Number *</label>
                                    <input type="text" class="form-control" v-model="state.paymentForm.phone_number"
                                        placeholder="0757058906 or 256771234567" required />
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Amount (UGX) *</label>
                                    <input type="number" class="form-control" v-model="state.paymentForm.amount"
                                        min="500" required />
                                    <small class="text-muted">Minimum: 500 UGX</small>
                                </div>
                                <button type="submit" class="btn btn-info w-100" :disabled="state.paymentLoading">
                                    <span v-if="state.paymentLoading" class="spinner-border spinner-border-sm me-2" role="status"></span>
                                    <i v-else class="fas fa-paper-plane me-2"></i>
                                    {{ state.paymentLoading ? 'Sending...' : 'Send Test Payment' }}
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Test Voucher Purchase Form -->
                <div class="col-md-6 mb-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-header bg-success text-white">
                            <i class="fas fa-ticket me-2"></i> Test Voucher Purchase
                        </div>
                        <div class="card-body">
                            <p class="text-muted small mb-3">
                                Test the complete voucher purchase flow. Creates a real voucher on successful payment.
                            </p>
                            <form @submit.prevent="testVoucherPurchase">
                                <div class="mb-3">
                                    <label class="form-label">Phone Number *</label>
                                    <input type="text" class="form-control" v-model="state.voucherForm.phone_number"
                                        placeholder="0757058906 or 256771234567" required />
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Router *</label>
                                    <select class="form-select" v-model="state.voucherForm.router_id"
                                        @change="onRouterChange(state.voucherForm.router_id)" required>
                                        <option value="">Select Router</option>
                                        <option v-for="router in state.routers" :key="router.id" :value="router.id">
                                            {{ router.name }}
                                        </option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Voucher Package *</label>
                                    <select class="form-select" v-model="state.voucherForm.package_id"
                                        :disabled="!state.voucherForm.router_id || state.packagesLoading" required>
                                        <option value="">{{ state.packagesLoading ? 'Loading packages...' : 'Select Package' }}</option>
                                        <option v-for="pkg in state.packages" :key="pkg.id" :value="pkg.id">
                                            {{ pkg.name }} - {{ formatCurrency(pkg.price) }}
                                        </option>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-success w-100" :disabled="state.voucherLoading">
                                    <span v-if="state.voucherLoading" class="spinner-border spinner-border-sm me-2" role="status"></span>
                                    <i v-else class="fas fa-shopping-cart me-2"></i>
                                    {{ state.voucherLoading ? 'Sending...' : 'Test Voucher Purchase' }}
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Test Results History -->
            <div class="card shadow-sm" v-if="state.testResults.length">
                <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-history me-2"></i> Test Results</span>
                    <button class="btn btn-sm btn-outline-light" @click="state.testResults = []">
                        <i class="fas fa-trash me-1"></i> Clear
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Time</th>
                                    <th>Phone</th>
                                    <th>Amount/Package</th>
                                    <th>Gateway</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(result, index) in state.testResults" :key="result.timestamp + index">
                                    <td>
                                        <span :class="result.type === 'payment' ? 'badge bg-info' : 'badge bg-success'">
                                            {{ result.type === 'payment' ? 'Payment' : 'Voucher' }}
                                        </span>
                                    </td>
                                    <td class="small">{{ formatDate(result.timestamp) }}</td>
                                    <td>{{ result.phone_number }}</td>
                                    <td>
                                        <span v-if="result.type === 'payment'">{{ formatCurrency(result.amount) }}</span>
                                        <span v-else>{{ result.package_name }}</span>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary text-uppercase">{{ result.gateway || '-' }}</span>
                                    </td>
                                    <td>
                                        <span class="badge" :class="getStatusBadgeClass(result.status)">
                                            {{ result.status }}
                                        </span>
                                        <div v-if="result.voucher" class="mt-1">
                                            <small class="text-success fw-bold">{{ result.voucher.code }}</small>
                                        </div>
                                    </td>
                                    <td>
                                        <button
                                            class="btn btn-sm btn-outline-primary"
                                            @click="recheckStatus(result, index)"
                                            title="Re-check Status">
                                            <i class="fas fa-sync-alt"></i>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Info Alert -->
            <div class="alert alert-info mt-4">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Note:</strong> These are real payment tests. The phone number you enter will receive an actual
                payment prompt. Status is checked automatically every 5 seconds for up to 3 minutes.
            </div>
        </template>
    </section>
</template>

<style scoped>
.card-header {
    font-weight: 600;
}

.badge {
    font-weight: 500;
}
</style>
