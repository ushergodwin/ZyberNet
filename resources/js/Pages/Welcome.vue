<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import { Head } from '@inertiajs/vue3';
import { showLoader, hideLoader, swalNotification } from '@/mixins/helpers.mixin.js';
import { notify } from '@/mixins/notify';
const packages = ref([]);
const phoneNumber = ref('');
const selectedPackage = ref(null);
const transactionId = ref(null);
const voucher = ref(null);
const checkingStatus = ref(false);
const processingPayment = ref(false);

const formatPhoneNumber = (number) => {
    // Format phone number to international format if needed
    /// if the number starts with 0, replace it with +256
    if (number.startsWith('0')) {
        return '+256' + number.slice(1);
    }
    // if phone number starts with 256 but not +256, replace it with +256
    if (number.startsWith('256') && !number.startsWith('+256')) {
        return '+256' + number.slice(3);
    }
    // if phone number does not start with +256 or 256 or 0 and is 9 digits long, prepend +256
    if (!number.startsWith('+256') && !number.startsWith('256') && !number.startsWith('0') && number.length === 9) {
        return '+256' + number;
    }
    // if phone number is already in international format, return it as is
    return number;
};
// Fetch available packages
async function fetchPackages() {
    try {
        showLoader();
        const response = await axios.get('/api/configuration/vouchers/packages');
        packages.value = response.data.packages;
        // set selected package to the first one if available
        if (packages.value.length > 0) {
            selectedPackage.value = packages.value[0].id;
        }
    } catch (error) {
        swalNotification('Failed to load packages', 'error');
    } finally {
        hideLoader();
    }
}

async function purchaseVoucher() {
    try {
        if (!phoneNumber.value) {
            swalNotification('warning', 'Please enter phone number');
            return;
        }
        if (!selectedPackage.value) {
            swalNotification('warning', 'Please select a package');
            return;
        }
        // phone number length must be >= 9 and <= 13
        if (phoneNumber.value.length < 9 || phoneNumber.value.length > 13) {
            swalNotification('warning', 'Phone number must be between 9 and 13 digits');
            return;
        }
        // format phone number
        phoneNumber.value = formatPhoneNumber(phoneNumber.value);
        processingPayment.value = true;
        showLoader();
        const response = await axios.post('/api/payments/voucher', {
            phone_number: phoneNumber.value,
            package_id: selectedPackage.value
        });
        hideLoader();
        swalNotification('success', response.data.message)
            .then(() => {
                transactionId.value = response.data.paymentData.id;
                checkingStatus.value = true;
                voucher.value = null; // Reset voucher
                checkTransactionStatus(); // Start checking status
            });
    } catch (error) {
        swalNotification('error', error.response?.data?.message || 'Payment failed');
    }
}

// Check transaction status periodically
function checkTransactionStatus() {
    if (!transactionId.value) return;
    checkingStatus.value = true;

    const interval = setInterval(async () => {
        try {
            const response = await axios.get(`/api/payments/voucher/status/${transactionId.value}`);
            if (response.data.voucher) {
                clearInterval(interval);
                voucher.value = response.data.voucher;
                swalNotification('success', 'Voucher is ready!');
                checkingStatus.value = false;
                processingPayment.value = false;
            }
            // if transaction.status is 'failed', clear interval and show error
            if (response.data.transaction?.status === 'failed') {
                clearInterval(interval);
                checkingStatus.value = false;
                processingPayment.value = false;
                swalNotification('error', 'Transaction failed. Please try again.');
            }
        } catch (err) {
            clearInterval(interval);
            checkingStatus.value = false;
            swalNotification('error', 'Error checking transaction status',);
        }
    }, 5000);
}

// copy voucher code to clipboard
const copyVoucherCode = () => {
    if (voucher.value) {
        navigator.clipboard.writeText(voucher.value.code)
            .then(() => notify.toastSuccessMessage('Voucher code copied to clipboard'))
            .catch(() => notify.toastErrorMessage('error', 'Failed to copy voucher code'));
    }
};
onMounted(() => {
    fetchPackages();
    // transactionId.value = 64661480;
    // checkTransactionStatus();
});
</script>

<template>

    <Head title="Buy Voucher" />
    <div class="min-vh-100 d-flex flex-column align-items-center justify-content-center gradient-bg text-white py-5">

        <!-- Logo and Heading -->
        <div class="text-center mb-4">
            <img src="@/assets/images/zybernet-short-logo.png" alt="Zybernet Logo" class="img-fluid mb-3 rounded-circle"
                style="max-width: 150px;">
            <h1 class="text-white">Buy Internet Voucher</h1>
            <p class="text-white">Purchase your internet voucher easily and securely.</p>
        </div>

        <!-- Card Section -->
        <div class="card shadow p-4 text-dark" style="width: 100%; max-width: 480px;">
            <div class="mb-3">
                <label class="form-label">Phone Number</label>
                <input type="text" class="form-control" v-model="phoneNumber" :disabled="processingPayment"
                    placeholder="e.g. +2567XXXXXXXX">
            </div>

            <div class="mb-3">
                <label class="form-label">Select Package</label>
                <select class="form-select" v-model="selectedPackage" :disabled="processingPayment">
                    <option disabled value="">-- Select --</option>
                    <option v-for="pkg in packages" :key="pkg.id" :value="pkg.id">
                        {{ pkg.name }} - {{ pkg.formatted_price }}
                        ({{ pkg.duration_hours }} {{ pkg.duration_hours > 1 ? `hrs` : `hr` }},
                        {{ pkg.speed_limit > 1 ? `${pkg.speed_limit} Mbps` : `Unlimited` }})
                    </option>
                </select>
            </div>

            <div class="d-grid">
                <button class="btn btn-primary" @click="purchaseVoucher" :disabled="processingPayment">
                    <span><i class="fas fa-shield-halved"></i> Pay Now</span>
                </button>
            </div>

            <div v-if="checkingStatus" class="text-center mt-3">
                <div class="spinner-border text-primary" role="status"></div>
                <p class="mt-2">Waiting for payment confirmation...</p>
            </div>

            <div v-if="voucher" class="alert alert-success mt-3">
                <h5>Your Voucher Code</h5>
                <div class="d-flex justify-content-between align-items-center">
                    <strong>{{ voucher.code }}</strong>
                    <button class="btn btn-sm btn-outline-dark" @click="copyVoucherCode">Copy</button>
                </div>
                <p class="mb-0 mt-2 small">Expires at: {{ new Date(voucher.expires_at).toLocaleString() }}</p>
            </div>
        </div>

    </div>
</template>

<style scoped>
.gradient-bg {
    background: linear-gradient(135deg, #5327ef 0%, #ff5f6d 100%) !important;
}
</style>