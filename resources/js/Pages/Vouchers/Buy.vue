<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import { Head } from '@inertiajs/vue3';
import { swalNotification } from '@/mixins/helpers.mixin.js';
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
    },
    supportContacts: {
        type: Array,
        default: () => []
    }
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
            swalNotification('warning', 'No package was selected. Please select a package to continue.')
                .then(() => {
                    window.history.back();
                });
            return;
        }

        const data = {
            phone_number: formatPhoneNumber(phoneNumber.value),
            package_id: packageId.value
        };

        processingPayment.value = true;
        paymentFailed.value = false;
        paymentTimedOut.value = false;
        paymentStatus.value = 'Initiating payment...';

        const response = await axios.post('/api/payments/voucher', data);

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
        swalNotification('error', error.response?.data?.message || 'Payment failed');
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
    const pollingInterval = 5000; // 5 seconds
    const timeoutDuration = 180000; // 3 minutes
    let elapsed = 0;

    pollOnce(); // First check immediately
    pollTimer = setInterval(pollOnce, pollingInterval);

    async function pollOnce() {
        elapsed += pollingInterval;

        try {
            const response = await axios.get(`/api/payments/voucher/status/${transactionId.value}`);
            const transaction = response.data.transaction;
            const voucherData = response.data.voucher;

            if (transaction?.status) {
                paymentStatus.value = statusLabels[transaction.status] || 'Processing...';
            }

            if (voucherData) {
                stopPolling();
                checkingStatus.value = false;
                processingPayment.value = false;
                voucher.value = voucherData;
                paymentStatus.value = statusLabels['successful'];
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
            .then(() => notify.toastSuccessMessage('Voucher copied to clipboard'))
            .catch(() => notify.toastErrorMessage('error', 'Failed to copy voucher code'));
    }
};

const connectToWiFi = () => {
    if (voucher.value?.code && props.link_login) {
        const form = document.getElementById('connect-to-wifi-form');
        form.username.value = voucher.value.code;
        form.password.value = voucher.value.code;
        form.submit();
    } else {
        notify.toastErrorMessage('error', `Connect to ${props.wifi_name} and use your voucher to access the internet.`);
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
            <div class="mb-3" v-if="!checkingStatus && !voucher && !paymentFailed && !paymentTimedOut">
                <label class="form-label">Phone Number</label>
                <input type="text" v-model="phoneNumber" class="form-control input-rounded"
                    :disabled="processingPayment" placeholder="+2567XXXXXXXX" />
            </div>

            <!-- Submit Button -->
            <div class="d-grid" v-if="!checkingStatus && !voucher && !paymentFailed && !paymentTimedOut">
                <button class="btn btn-gradient text-white fw-bold" @click="purchaseVoucher"
                    :disabled="processingPayment">
                    <span v-if="processingPayment" class="spinner-border spinner-border-sm me-2" role="status"></span>
                    <i v-else class="fas fa-wallet me-2"></i>
                    {{ processingPayment ? 'Sending...' : 'Pay Now' }}
                </button>
            </div>

            <!-- Payment Status Progress -->
            <div v-if="checkingStatus" class="text-center mt-4">
                <div class="spinner-border text-light mb-3" role="status"></div>
                <p class="fw-bold mb-1">{{ paymentStatus }}</p>
                <p class="small text-white-50">
                    Do not close or refresh this page.
                </p>
            </div>

            <!-- Payment Failed -->
            <div v-if="paymentFailed" class="mt-4 text-center">
                <div class="mb-2" style="font-size: 2.5rem;">&#10060;</div>
                <p class="fw-bold text-danger">Payment Failed</p>
                <p class="small text-white-50">The transaction could not be completed. Please try again.</p>
                <button class="btn btn-gradient text-white fw-bold mt-2" @click="retryPayment">
                    <i class="fas fa-redo me-2"></i> Try Again
                </button>
            </div>

            <!-- Payment Timed Out -->
            <div v-if="paymentTimedOut" class="mt-4 text-center">
                <div class="mb-2" style="font-size: 2.5rem;">&#9888;&#65039;</div>
                <p class="fw-bold" style="color: #ffc107;">{{ paymentStatus }}</p>
                <p class="small text-white-50">
                    Please contact IT support for assistance. Your payment may still be processing.
                </p>
                <div v-if="props.supportContacts?.length" class="small mt-2">
                    <span v-for="contact in props.supportContacts?.filter(c => c.type == 'Tel')" :key="'to-' + contact?.id">
                        Call <a :href="`tel:${contact?.formatted_phone_number}`" class="text-info">{{ contact?.phone_number }}</a>&nbsp;
                    </span>
                    <br />
                    <span v-for="contact in props.supportContacts?.filter(c => c.type == 'WhatsApp')" :key="'wo-' + contact?.id">
                        WhatsApp <a :href="`https://wa.me/${contact?.formatted_phone_number}`" target="_blank" class="text-info">{{ contact?.phone_number }}</a>&nbsp;
                    </span>
                </div>
                <button class="btn btn-gradient text-white fw-bold mt-3" @click="retryPayment">
                    <i class="fas fa-redo me-2"></i> Try Again
                </button>
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
                <div class="d-flex justify-content-end mt-3 gap-3">
                    <button class="btn btn-outline-primary btn-sm" @click="copyVoucherCode">
                        <i class="fas fa-copy"></i> Copy Voucher
                    </button>
                    <button class="btn btn-outline-primary btn-sm ms-2" @click="connectToWiFi" v-if="props.link_login">
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
            <hr />
            <div v-if="props.supportContacts?.length"><strong>Need help?</strong></div>
            <div v-if="props.supportContacts?.length" class="d-flex gap-1"> Call
                <section v-for="contact in props.supportContacts?.filter(c => c.type == 'Tel')"
                    :key="'t-' + contact?.id">
                    <a :href="`tel:${contact?.formatted_phone_number}`">{{ contact?.phone_number }}</a>
                </section>
            </div>
            <div v-if="props.supportContacts?.length" class="d-flex gap-1">WhatsApp
                <section v-for="contact in props.supportContacts?.filter(c => c.type == 'WhatsApp')"
                    :key="'w-' + contact?.id">
                    <a :href="`https://wa.me/${contact?.formatted_phone_number}`" target="_blank"
                        class="d-flex gap-3">{{
                            contact?.phone_number
                        }}</a>
                </section>
            </div>
            <!-- Branding -->
            <div class="text-center mt-4 small text-white-50">
                Powered by <span class="text-info fw-bold">Eng. Godwin</span>
            </div>
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
