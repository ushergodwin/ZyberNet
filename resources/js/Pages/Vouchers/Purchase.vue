<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import { Head } from '@inertiajs/vue3';
import { swalNotification } from '@/mixins/helpers.mixin.js';
import { notify } from '@/mixins/notify';
import AdminLayout from "@/Layouts/AdminLayout.vue";
defineOptions({ layout: AdminLayout });

const props = defineProps({
    package_id: {
        type: Number,
        default: null
    },
    csrfToken: {
        type: String,
        default: ""
    },
    packages: {
        type: Array,
        default: () => []
    },
    supportContacts: {
        type: Array,
        default: () => []
    },
});

const phoneNumber = ref('');
const transactionId = ref(null);
const voucher = ref(null);
const checkingStatus = ref(false);
const processingPayment = ref(false);
const paymentStatus = ref('');
const paymentFailed = ref(false);
const paymentTimedOut = ref(false);
let packageId = ref(props.package_id);
const iHaveAVoucher = ref(false);
const userVoucherCode = ref('');
let pollTimer = null;

const statusLabels = {
    new: 'Payment initiated...',
    pending: 'Waiting for confirmation...',
    instructions_sent: 'Please enter your PIN on your phone...',
    processing_started: 'Processing your payment...',
    successful: 'Payment successful!',
    failed: 'Payment failed.',
};

const formatPhoneNumber = (number) => {
    if (number.startsWith('0')) {
        return '+256' + number.slice(1);
    }
    if (number.startsWith('256') && !number.startsWith('+256')) {
        return '+256' + number.slice(3);
    }
    if (!number.startsWith('+256') && !number.startsWith('256') && !number.startsWith('0') && number.length === 9) {
        return '+256' + number;
    }
    return number;
};

async function purchaseVoucher() {
    try {
        if (!phoneNumber.value) {
            swalNotification('warning', 'Please enter phone number');
            return;
        }
        if (phoneNumber.value.length < 9 || phoneNumber.value.length > 13) {
            swalNotification('warning', 'Phone number must be between 9 and 13 digits');
            return;
        }
        if (!packageId.value) {
            swalNotification('warning', 'No package was selected. Please select a package to continue.');
            return;
        }

        phoneNumber.value = formatPhoneNumber(phoneNumber.value);
        processingPayment.value = true;
        paymentFailed.value = false;
        paymentTimedOut.value = false;
        paymentStatus.value = 'Initiating payment...';

        const payload = {
            phone_number: phoneNumber.value,
            package_id: packageId.value,
            voucher_code: iHaveAVoucher.value ? userVoucherCode.value : null,
        };

        const response = await axios.post('/api/payments/voucher', payload);

        if (response.status === 200) {
            transactionId.value = response.data.paymentData.id;
            checkingStatus.value = true;
            voucher.value = null;
            paymentStatus.value = statusLabels['instructions_sent'];
            checkTransactionStatus();
        } else {
            processingPayment.value = false;
            paymentStatus.value = '';
            swalNotification('error', 'Failed to initiate payment. Please try again.');
        }
    } catch (error) {
        processingPayment.value = false;
        paymentStatus.value = '';
        swalNotification('error', error.response?.data?.message || error.message || 'Payment failed');
    }
}

function stopPolling() {
    if (pollTimer) {
        clearInterval(pollTimer);
        pollTimer = null;
    }
}

function checkTransactionStatus() {
    if (!transactionId.value) return;

    checkingStatus.value = true;
    const pollingInterval = 5000;
    const timeoutDuration = 180000; // 3 minutes
    let elapsed = 0;

    pollOnce();
    pollTimer = setInterval(pollOnce, pollingInterval);

    async function pollOnce() {
        elapsed += pollingInterval;

        try {
            const response = await axios.get(`/api/payments/voucher/status/${transactionId.value}?voucher_code=${userVoucherCode.value || ''}`);
            const transaction = response.data.transaction;
            const voucherData = response.data.voucher;

            if (transaction?.status) {
                paymentStatus.value = statusLabels[transaction.status] || 'Processing...';
            }

            if (voucherData) {
                stopPolling();
                voucher.value = voucherData;
                checkingStatus.value = false;
                processingPayment.value = false;
                paymentStatus.value = statusLabels['successful'];
                swalNotification('success', 'Voucher is ready!')
                    .then(() => {
                        document.getElementById('routerLoginForm')?.submit();
                    });
                return;
            }

            if (transaction?.status === 'failed') {
                stopPolling();
                checkingStatus.value = false;
                processingPayment.value = false;
                paymentFailed.value = true;
                paymentStatus.value = statusLabels['failed'];
                return;
            }

            if (elapsed >= timeoutDuration) {
                stopPolling();
                checkingStatus.value = false;
                processingPayment.value = false;
                paymentTimedOut.value = true;
                paymentStatus.value = 'Payment is taking too long.';
            }
        } catch (err) {
            if (elapsed >= timeoutDuration) {
                stopPolling();
                checkingStatus.value = false;
                processingPayment.value = false;
                paymentTimedOut.value = true;
                paymentStatus.value = 'Could not verify payment status.';
            }
        }
    }
}

function retryPayment() {
    paymentFailed.value = false;
    paymentTimedOut.value = false;
    paymentStatus.value = '';
    transactionId.value = null;
    voucher.value = null;
}

const copyVoucherCode = () => {
    if (voucher.value) {
        navigator.clipboard.writeText(voucher.value.code)
            .then(() => notify.toastSuccessMessage('Voucher code copied to clipboard'))
            .catch(() => notify.toastErrorMessage('error', 'Failed to copy voucher code'));
    }
};

const printVoucherCode = () => {
    if (voucher.value) {
        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <html>
                <head>
                    <title>SuperSpot Wifi</title>
                    <style>
                        body { font-family: Arial, sans-serif; }
                        .voucher-code { font-size: 24px; font-weight: bold; }
                    </style>
                </head>
                <body>
                    <center>
                        <div class="voucher-code">${voucher.value.code}</div>
                        <p>Expires at: ${new Date(voucher.value.expires_at).toLocaleString()}</p>
                    </center>
                </body>
            </html>
        `);
        printWindow.document.close();
        printWindow.print();
    }
};

onMounted(() => {
    if (props.package_id) {
        packageId.value = props.package_id;
    }
});

onUnmounted(() => {
    stopPolling();
});
</script>
<template>

    <Head title="Buy Voucher" />
    <div class="d-flex flex-column align-items-center justify-content-center gradient-bg py-5 text-white rounded">

        <!-- Logo and Title -->
        <div class="text-center mt-1">
            <img src="@/assets/images/superspotwifi-logo.png" alt="SuperSpot Wifi Logo"
                class="img-fluid mb-3 rounded-circle logo" />
            <h2 class="fw-bold">SuperSpot Wifi</h2>
            <p class="small text-light">Buy your internet voucher easily and securely.</p>
        </div>

        <!-- Voucher Card -->
        <div class="voucher-card p-4 w-100 text-white">
            <!-- Package Selection -->
            <div class="mb-3" v-if="!checkingStatus && !paymentFailed && !paymentTimedOut">
                <label class="form-label">Select Package</label>
                <select v-model="packageId" class="form-select input-rounded" :disabled="processingPayment">
                    <option :value="null" disabled>Select a package</option>
                    <option v-for="pkg in props.packages" :key="pkg.id" :value="pkg.id">
                        {{ pkg.name }} - UGX {{ pkg.price }}
                    </option>
                </select>
            </div>
            <!-- Phone Input -->
            <div class="mb-3" v-if="!checkingStatus && !paymentFailed && !paymentTimedOut">
                <label class="form-label">Phone Number</label>
                <input type="text" v-model="phoneNumber" class="form-control input-rounded"
                    :disabled="processingPayment" placeholder="+2567XXXXXXXX" />
            </div>
            <div class="form-check" v-if="!iHaveAVoucher && !checkingStatus && !paymentFailed && !paymentTimedOut">
                <input type="checkbox" class="form-check-input" v-model="iHaveAVoucher" name="customCheck"
                    id="customCheck1">
                <label class="form-check-label" for="customCheck1">I already have a voucher</label>
            </div>
            <!-- Voucher Code Input -->
            <div class="mb-3" v-if="iHaveAVoucher && !checkingStatus && !paymentFailed && !paymentTimedOut">
                <label class="form-label">Enter Voucher Code</label>
                <div class="input-group">
                    <input type="text" v-model="userVoucherCode" class="form-control input-rounded"
                        placeholder="Enter voucher code" :disabled="processingPayment" />
                    <button class="btn btn-outline-secondary" @click="iHaveAVoucher = !iHaveAVoucher">
                        <i class="fas fa-exchange-alt"></i>
                    </button>
                </div>
            </div>
            <!-- Submit Button -->
            <div class="d-grid mt-3" v-if="!checkingStatus && !voucher && !paymentFailed && !paymentTimedOut">
                <button class="btn btn-gradient text-white fw-bold" @click="purchaseVoucher"
                    :disabled="processingPayment">
                    <span v-if="processingPayment" class="spinner-border spinner-border-sm me-2" role="status"></span>
                    <i v-else class="fas fa-wallet me-2"></i>
                    {{ processingPayment ? 'Sending...' : 'Send Payment Prompt' }}
                </button>
            </div>

            <!-- Payment Status Progress -->
            <div v-if="checkingStatus" class="text-center mt-4">
                <div class="spinner-border text-light mb-3" role="status"></div>
                <p class="fw-bold mb-1">{{ paymentStatus }}</p>
                <p class="small text-white-50">Do not close or refresh this page.</p>
            </div>

            <!-- Payment Failed -->
            <div v-if="paymentFailed" class="mt-4 text-center">
                <p class="fw-bold text-danger">Payment Failed</p>
                <p class="small text-white-50">The transaction could not be completed. Please try again.</p>
                <button class="btn btn-gradient text-white fw-bold mt-2" @click="retryPayment">
                    <i class="fas fa-redo me-2"></i> Try Again
                </button>
            </div>

            <!-- Payment Timed Out -->
            <div v-if="paymentTimedOut" class="mt-4 text-center">
                <p class="fw-bold" style="color: #ffc107;">{{ paymentStatus }}</p>
                <p class="small text-white-50">
                    Please contact IT support for assistance. Your payment may still be processing.
                </p>
                <button class="btn btn-gradient text-white fw-bold mt-3" @click="retryPayment">
                    <i class="fas fa-redo me-2"></i> Try Again
                </button>
            </div>

            <!-- Voucher Code Result -->
            <div v-if="voucher" class="alert alert-success mt-4">
                <h5>Your Voucher Code</h5>
                <div class="mt-1">
                    <div class="d-flex justify-content-between align-items-center">
                        <strong class="text-uppercase">{{ voucher?.code }}</strong>
                    </div>
                    <p class="mb-0 mt-2 small">Expires at: {{ voucher?.expires_at ? new
                        Date(voucher.expires_at).toLocaleString() : '' }}</p>
                </div>
                <div class="d-flex justify-content-end mt-3 gap-3">
                    <button class="btn btn-outline-success btn-sm" @click="copyVoucherCode">
                        <i class="fas fa-copy"></i> Copy Code
                    </button>
                    <button class="btn btn-outline-success btn-sm ms-2" @click="printVoucherCode">
                        <i class="fas fa-print"></i> Print Voucher
                    </button>
                </div>
            </div>
        </div>

        <!-- Branding -->
        <div class="text-center mt-4 small text-white-50">
            Powered by <span class="text-info fw-bold">Eng. Godwin</span>
        </div>
    </div>
</template>

<style scoped>
.gradient-bg {
    background: linear-gradient(135deg, #5327ef 0%, #ff5f6d 100%) !important;
}

.logo {
    width: 100px;
}

.voucher-card {
    background-color: #0d0c20;
    border-radius: 20px;
    max-width: 400px;
    width: 100%;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.4);
}

.input-rounded {
    border-radius: 30px;
    padding-left: 1rem;
}

.btn-gradient {
    background: linear-gradient(90deg, #00f0ff, #ff00c8);
    border: none;
    border-radius: 30px;
}

.btn-gradient:hover {
    opacity: 0.9;
}
</style>
