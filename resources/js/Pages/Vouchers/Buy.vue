<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import { Head } from '@inertiajs/vue3';
import { showLoader, hideLoader, swalNotification } from '@/mixins/helpers.mixin.js';
import { notify } from '@/mixins/notify';
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
    link_login: {
        type: String,
        default: ""
    },
    wifi_name: {
        type: String,
        default: "SuperSpot Wifi"
    }
});
const phoneNumber = ref('');
const transactionId = ref(null);
const voucher = ref(null);
const checkingStatus = ref(false);
const processingPayment = ref(false);
let packageId = ref(props.package_id);
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
async function purchaseVoucher() {
    try {
        if (!phoneNumber.value) {
            swalNotification('warning', 'Please enter phone number');
            return;
        }
        // phone number length must be >= 9 and <= 13
        if (phoneNumber.value.length < 9 || phoneNumber.value.length > 13) {
            swalNotification('warning', 'Phone number must be between 9 and 13 digits');
            return;
        }
        if (!packageId.value) {
            swalNotification('warning', 'No package was selected. Please select a package to continue.')
                .then(() => {
                    window.history.back();
                });
            return;
        }
        // format phone number
        phoneNumber.value = formatPhoneNumber(phoneNumber.value);
        processingPayment.value = true;
        showLoader();
        const response = await axios.post('/api/payments/voucher', {
            phone_number: phoneNumber.value,
            package_id: packageId
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
        console.error('Payment error:', error);
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
                swalNotification('success', 'Voucher is ready!')
                    .then(() => {
                        checkingStatus.value = false;
                        processingPayment.value = false;
                        // submit the voucher code to the router login form
                        document.getElementById('routerLoginForm').submit();
                    });

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
    }, // every minute
        60000
    );
}

// copy voucher code to clipboard
const copyVoucherCode = () => {
    if (voucher.value) {
        navigator.clipboard.writeText(voucher.value.code)
            .then(() => notify.toastSuccessMessage('Voucher copied to clipboard'))
            .catch(() => notify.toastErrorMessage('error', 'Failed to copy voucher code'));
    }
};

// connect to WiFi
const connectToWiFi = () => {
    if (voucher.value?.code && props.link_login) {
        // submit the voucher code to the router login form
        const form = document.getElementById('connect-to-wifi-form');
        form.username.value = voucher.value.code;
        form.password.value = voucher.value.code;
        form.submit();
    } else {
        notify.toastErrorMessage('error', `Connect to ${props.wifi_name} and use your voucher to access the internet.`);
    }
};
onMounted(() => {
    // transactionId.value = 64661480;
    // checkTransactionStatus();
    if (props.package_id) {
        packageId.value = props.package_id;
    }

});
</script>
<template>

    <Head title="Buy Voucher" />
    <div class="min-vh-100 d-flex flex-column align-items-center justify-content-center gradient-bg py-5 text-white">

        <!-- Logo and Title -->
        <div class="text-center mb-4">
            <img src="@/assets/images/superspotwifi-logo.png" alt="SuperSpot Wifi Logo"
                class="img-fluid mb-3 rounded-circle logo" />
            <h2 class="fw-bold">{{ props.wifi_name }}</h2>
            <p class="small text-light">Buy your internet voucher easily and securely.</p>
        </div>

        <!-- Voucher Card -->
        <div class="voucher-card p-4 w-100 text-white">
            <!-- Package Selection -->
            <div class="mb-3" v-if="!packageId">
                <label class="form-label">Select Package</label>
                <select v-model="packageId" class="form-select input-rounded" :disabled="processingPayment">
                    <option :value="null" disabled>Select a package</option>
                    <option v-for="pkg in props.packages" :key="pkg.id" :value="pkg.id">
                        {{ pkg.name }} - UGX {{ pkg.price }}
                    </option>
                </select>
            </div>
            <!-- Phone Input -->
            <div class="mb-3">
                <label class="form-label">Phone Number</label>
                <input type="text" v-model="phoneNumber" class="form-control input-rounded"
                    :disabled="processingPayment" placeholder="+2567XXXXXXXX" />
            </div>

            <!-- Submit Button -->
            <div class="d-grid">
                <button class="btn btn-gradient text-white fw-bold" @click="purchaseVoucher"
                    :disabled="processingPayment">
                    <i class="fas fa-wallet me-2"></i> Pay Now
                </button>
            </div>

            <!-- Payment Spinner -->
            <div v-if="checkingStatus" class="text-center mt-4">
                <div class="spinner-border text-light" role="status"></div>
                <p class="mt-2 small text-white-50">Waiting for payment confirmation...</p>
            </div>

            <!-- Voucher Code Result -->
            <div v-if="voucher" class="alert alert-success mt-4">
                <h5>Your Voucher</h5>
                <div class="mt-1">
                    <div class="d-flex justify-content-between align-items-center">
                        <strong class="text-uppercase">{{ voucher.code }}</strong>
                    </div>
                    <p class="mb-0 mt-2 small">Expires at: {{ new Date(voucher.expires_at).toLocaleString() }}</p>
                </div>
                <!-- print voucher code -->
                <div class="d-flex justify-content-end mt-3 gap-3">
                    <button class="btn btn-outline-light btn-sm" @click="copyVoucherCode">
                        <i class="fas fa-copy"></i> Copy Voucher
                    </button>
                    <!-- connect to WiFi -->
                    <button class="btn btn-outline-light btn-sm ms-2" @click="connectToWiFi">
                        <i class="fas fa-print"></i> Connect to WiFi
                    </button>
                    <form method="POST" :action="props.link_login" id="connect-to-wifi-form">
                        <input type="hidden" name="_token" :value="props.csrfToken">
                        <input type="hidden" name="username" :value="voucher?.code">
                        <input type="hidden" name="password" :value="voucher?.code">
                        <input type="hidden" name="dst" value="https://www.google.com" />
                    </form>
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
