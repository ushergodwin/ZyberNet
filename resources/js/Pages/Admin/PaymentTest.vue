<script setup lang="ts">
import { ref, reactive, onMounted, computed } from "vue";
import { usePage } from "@inertiajs/vue3";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import { showLoader, hideLoader, swalNotification, hasPermission } from "@/mixins/helpers.mixin.js";
import axios from "axios";

defineOptions({ layout: AdminLayout });

const state = reactive({
    // Gateway info
    gatewayInfo: null as any,
    gatewayLoading: false,

    // Test payment form
    paymentForm: {
        phone_number: '',
        amount: 5000,
    },
    paymentLoading: false,

    // Voucher purchase test form
    voucherForm: {
        phone_number: '',
        package_id: '',
    },
    voucherLoading: false,

    // Available packages
    packages: [] as any[],
    packagesLoading: false,

    // Test results history
    testResults: [] as any[],

    // Current user
    currentUser: null as any,

    // Active tab
    activeTab: 'payment',
});

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

// Load voucher packages
const loadPackages = async () => {
    state.packagesLoading = true;
    try {
        const response = await axios.get('/api/configuration/vouchers/packages');
        state.packages = response.data.filter((p: any) => p.is_active);
    } catch (error) {
        console.error('Failed to load packages:', error);
    } finally {
        state.packagesLoading = false;
    }
};

// Test payment (no voucher)
const testPayment = async () => {
    if (!state.paymentForm.phone_number || !state.paymentForm.amount) {
        swalNotification('error', 'Please fill in all required fields');
        return;
    }

    state.paymentLoading = true;
    showLoader('Initiating test payment...');

    try {
        const response = await axios.post('/api/admin/test/payment', state.paymentForm);
        hideLoader();

        const result = {
            type: 'payment',
            ...response.data,
            timestamp: new Date().toISOString(),
            phone_number: state.paymentForm.phone_number,
            amount: state.paymentForm.amount,
        };

        state.testResults.unshift(result);

        if (response.data.success) {
            swalNotification('success', 'Test payment initiated successfully. Check the results below.');
        } else {
            swalNotification('error', response.data.message || 'Payment test failed');
        }
    } catch (error: any) {
        hideLoader();
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
    showLoader('Initiating voucher purchase test...');

    try {
        const response = await axios.post('/api/admin/test/voucher-purchase', state.voucherForm);
        hideLoader();

        const selectedPackage = state.packages.find(p => p.id == state.voucherForm.package_id);

        const result = {
            type: 'voucher',
            ...response.data,
            timestamp: new Date().toISOString(),
            phone_number: state.voucherForm.phone_number,
            package_name: selectedPackage?.name || 'Unknown',
        };

        state.testResults.unshift(result);

        if (response.data.success) {
            swalNotification('success', 'Voucher purchase test initiated. Payment prompt sent to phone.');
        } else {
            swalNotification('error', response.data.message || 'Voucher purchase test failed');
        }
    } catch (error: any) {
        hideLoader();
        console.error('Voucher purchase test error:', error);
        swalNotification('error', error.response?.data?.message || 'Failed to initiate voucher purchase test');
    } finally {
        state.voucherLoading = false;
    }
};

// Check payment status
const checkPaymentStatus = async (result: any) => {
    const reference = result.payment_id || result.payment_data?.id || result.transaction_id;
    if (!reference) {
        swalNotification('error', 'No payment reference found');
        return;
    }

    showLoader('Checking payment status...');

    try {
        const response = await axios.get(`/api/admin/test/payment/status/${reference}`);
        hideLoader();

        // Update the result in the list
        const index = state.testResults.findIndex(r =>
            (r.payment_id === reference) || (r.payment_data?.id === reference) || (r.transaction_id === reference)
        );

        if (index !== -1) {
            state.testResults[index] = {
                ...state.testResults[index],
                ...response.data,
                status: response.data.transaction?.status || response.data.status,
                lastChecked: new Date().toISOString(),
            };
        }

        const status = response.data.transaction?.status || response.data.status;
        if (status === 'successful') {
            swalNotification('success', 'Payment successful!');
        } else if (status === 'failed') {
            swalNotification('error', 'Payment failed');
        } else {
            swalNotification('info', `Payment status: ${status}`);
        }
    } catch (error: any) {
        hideLoader();
        console.error('Status check error:', error);
        swalNotification('error', error.response?.data?.message || 'Failed to check payment status');
    }
};

// Clear test history
const clearHistory = () => {
    state.testResults = [];
    swalNotification('success', 'Test history cleared');
};

onMounted(() => {
    const token = usePage().props.auth?.user?.api_token;
    if (token) {
        axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
    }
    state.currentUser = usePage().props.auth?.user;

    if (canTestPayments.value) {
        loadGatewayInfo();
        loadPackages();
    }
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
                                        placeholder="256771234567" required />
                                    <small class="text-muted">Format: 256XXXXXXXXX</small>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Amount (UGX) *</label>
                                    <input type="number" class="form-control" v-model="state.paymentForm.amount"
                                        min="500" required />
                                    <small class="text-muted">Minimum: 500 UGX</small>
                                </div>
                                <button type="submit" class="btn btn-info w-100" :disabled="state.paymentLoading">
                                    <i class="fas fa-paper-plane me-2"></i>
                                    {{ state.paymentLoading ? 'Processing...' : 'Send Test Payment' }}
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
                                        placeholder="256771234567" required />
                                    <small class="text-muted">Format: 256XXXXXXXXX</small>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Voucher Package *</label>
                                    <select class="form-select" v-model="state.voucherForm.package_id" required>
                                        <option value="">Select Package</option>
                                        <option v-for="pkg in state.packages" :key="pkg.id" :value="pkg.id">
                                            {{ pkg.name }} - {{ formatCurrency(pkg.price) }}
                                        </option>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-success w-100" :disabled="state.voucherLoading">
                                    <i class="fas fa-shopping-cart me-2"></i>
                                    {{ state.voucherLoading ? 'Processing...' : 'Test Voucher Purchase' }}
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Test Results -->
            <div class="card shadow-sm">
                <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-history me-2"></i> Test Results</span>
                    <button class="btn btn-sm btn-outline-light" @click="clearHistory" v-if="state.testResults.length">
                        <i class="fas fa-trash me-1"></i> Clear
                    </button>
                </div>
                <div class="card-body">
                    <div v-if="!state.testResults.length" class="text-center text-muted py-4">
                        <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                        No test results yet. Run a test above to see results here.
                    </div>

                    <div v-else class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Time</th>
                                    <th>Phone</th>
                                    <th>Amount/Package</th>
                                    <th>Gateway</th>
                                    <th>Status</th>
                                    <th>Reference</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(result, index) in state.testResults" :key="index">
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
                                        <span class="badge bg-secondary text-uppercase">
                                            {{ result.gateway || result.payment_data?._gateway || '-' }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge" :class="getStatusBadgeClass(result.status || result.payment_data?.status || 'pending')">
                                            {{ result.status || result.payment_data?.status || 'pending' }}
                                        </span>
                                    </td>
                                    <td class="small text-muted">
                                        {{ result.payment_id || result.payment_data?.id || result.transaction_id || '-' }}
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" @click="checkPaymentStatus(result)"
                                            title="Check Status">
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
                payment prompt. Make sure to use valid test numbers.
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
