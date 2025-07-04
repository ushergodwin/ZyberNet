<script setup lang="ts">
import { ref, reactive, onMounted, onUnmounted, nextTick } from "vue";
import { Head, router, usePage } from "@inertiajs/vue3";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import { showLoader, hideLoader, swalNotification, swalConfirm } from "@/mixins/helpers.mixin.js";
import axios from "axios";
import emitter from "@/eventBus";
defineOptions({ layout: AdminLayout });

onMounted(() => {
    const token = usePage().props.auth.user.api_token;
    if (token) axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
    loadVouchers(1);
    loadPackages();
    emitter.on('search', handleSearch);

    // Load last generated from local storage
    const cached = localStorage.getItem("recentlyGeneratedVouchers");
    if (cached && cached !== 'undefined') {
        state.generatedVouchers = JSON.parse(cached);
    }
});
onUnmounted(() => emitter.off('search', handleSearch));

const state = reactive({
    vouchers: [],
    loading: false,
    error: null,
    pagination: { current_page: 0, last_page: 0, per_page: 10, total: 0, from: 0, to: 0 },
    searchQuery: '',
    voucherTransaction: null,
    showTransactionModal: false,
    selectedVoucher: null,
    form: { package_id: null, quantity: 1 },
    showCreateModal: false,
    packages: [],
    generatedVouchers: [],
    showPrintModal: false,
    transactionForm: {
        phone_number: '',
        currency: 'UGX',
        amount: 0,
        status: 'successful',
    }
});

const loadVouchers = (page) => {
    if (state.loading) return;
    state.loading = true;
    let url = `/api/vouchers?page=${page}`;
    if (state.searchQuery) url += `&search=${encodeURIComponent(state.searchQuery)}`;
    axios.get(url).then(response => {
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
        state.error = data.data.length === 0 ? "No vouchers found at the moment!." : null;
        state.loading = false;
    }).catch(error => {
        state.error = "Failed to load vouchers.";
        console.error(error);
        state.loading = false;
    });
};

const loadPackages = () => {
    axios.get('/api/configuration/vouchers/packages')
        .then(response => state.packages = response.data.packages)
        .catch(() => swalNotification("error", "Failed to load packages"));
};

const generateVouchers = () => {
    if (!state.form.package_id) {
        swalNotification("error", "Please select a package and try again.");
        return;
    }
    swalConfirm.fire({
        title: "Generate Vouchers",
        text: `Generate ${state.form.quantity} vouchers?`,
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Yes, generate!",
        reverseButtons: true,
    }).then(result => {
        if (result.isConfirmed) {
            showLoader('generating...');
            axios.post('/api/vouchers/generate', {
                package_id: state.form.package_id,
                quantity: state.form.quantity
            }).then(response => {
                hideLoader();
                state.generatedVouchers = response.data.vouchers;
                localStorage.setItem("recentlyGeneratedVouchers", JSON.stringify(state.generatedVouchers));
                state.showCreateModal = false;
                state.form.package_id = null;
                state.form.quantity = 1;
                swalNotification("success", `${state.generatedVouchers.length} vouchers generated.`).then(() => {
                    state.showPrintModal = true;
                    nextTick(() => printVouchers(state.generatedVouchers));
                    loadVouchers(1);
                });
            }).catch(error => {
                hideLoader();
                swalNotification("error", "Failed to generate vouchers.");
                console.error(error);
            });
        }
    });
};

const printVouchers = (vouchers) => {
    const printableContent = vouchers.map(v =>
        `<div class="voucher-print">
            <strong>${v.code}</strong><br>
            ${v.formatted_expiry_date || 'No expiry'}
        </div><hr class="cut-line" />`
    ).join("");

    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
            <head>
                <title>SuperSport Wifi Voucher</title>
                <style>
                    body { font-family: Arial, sans-serif; padding: 20px; }
                    .voucher-print { font-size: 20px; text-align: center; margin-bottom: 10px; }
                    .cut-line { border-top: 1px dashed #999; margin: 20px 0; }
                </style>
            </head>
            <body>${printableContent}</body>
        </html>
    `);
    printWindow.document.close();
    printWindow.print();
};

const printSingleVoucher = (voucher) => {
    printVouchers([voucher]);
};

const reprintLastBatch = () => {
    const cached = localStorage.getItem("recentlyGeneratedVouchers");
    if (cached) {
        const vouchers = JSON.parse(cached);
        printVouchers(vouchers);
    } else {
        swalNotification("info", "No recent vouchers found in memory.");
    }
};

const handleSearch = (query) => {
    state.searchQuery = query;
    loadVouchers(1);
};

const handlePageChange = (page) => {
    if (page < 1 || page > state.pagination.last_page) return;
    loadVouchers(page);
};

const getVoucherTransaction = (voucher) => {
    state.voucherTransaction = null;
    state.selectedVoucher = voucher;
    state.transactionForm.amount = voucher.package.price;
    state.showTransactionModal = false;
    showLoader();
    axios.get(`/api/vouchers/${voucher.id}/transaction`)
        .then(response => {
            hideLoader();
            state.voucherTransaction = response.data.transaction;
            state.showTransactionModal = true;
        })
        .catch(error => {
            hideLoader();
            swalNotification("error", "Failed to load transaction.");
            console.error(error);
        });
};

//saveTransaction
const saveTransaction = () => {
    swalConfirm.fire({
        title: "Save Transaction",
        text: `Save transaction for voucher ${state.selectedVoucher.code}?`,
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Yes, save!",
        reverseButtons: true,
    }).then(result => {
        if (result.isConfirmed) {
            showLoader('Saving transaction...');
            axios.post(`/api/vouchers/${state.selectedVoucher.id}/transaction`, state.transactionForm)
                .then(response => {
                    if (response.status === 200) {
                        hideLoader();
                        swalNotification("success", "Transaction saved successfully.");
                        state.showTransactionModal = false;
                        loadVouchers(state.pagination.current_page);
                    } else {
                        hideLoader();
                        swalNotification("error", "Failed to save transaction.");
                    }
                })
                .catch(error => {
                    hideLoader();
                    swalNotification("error", "Failed to save transaction.");
                    console.error(error);
                });
        }
    });
}

const goToPurchase = () => {
    router.visit('/vouchers/purchase');
};

// delete voucher 
const sendDeleteVoucherRequest = () => {
    showLoader('Deleting voucher...');
    axios.delete(`/api/vouchers/${state.selectedVoucher.code}`)
        .then(response => {
            hideLoader();
            if (response.status === 200) {
                swalNotification("success", response.data.message || "Voucher deleted successfully.");
                loadVouchers(state.pagination.current_page);
            } else {
                swalNotification("error", "Failed to delete voucher.");
            }
        })
        .catch(error => {
            hideLoader();
            swalNotification("error", "Failed to delete voucher.");
            console.error(error);
        });
}

const deleteVoucher = (voucher) => {
    state.selectedVoucher = voucher;
    swalConfirm.fire({
        title: "Delete Voucher",
        text: `Are you sure you want to delete this voucher? It will also be deleted from the router and this action cannot be undone.`,
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Yes, delete!",
        reverseButtons: true,
    }).then(result => {
        if (result.isConfirmed) {
            sendDeleteVoucherRequest();
        }
    });
};
</script>


<template>
    <section class="container-fluid">

        <Head title="Vouchers" />
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="h3">Vouchers</h4>
            <div class="d-flex gap-2">
                <button class="btn btn-success" @click="goToPurchase">
                    <i class="fas fa-dollar"></i> Purchase
                </button>
                <button class="btn btn-primary" @click="state.showCreateModal = true">
                    <i class="fas fa-plus"></i> Create Vouchers
                </button>
                <button class="btn btn-secondary" @click="reprintLastBatch">
                    <i class="fas fa-print"></i> Reprint Last Batch
                </button>
            </div>
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
                        <td>{{ voucher.expires_at ? voucher.formatted_expiry_date : `N/A` }}</td>
                        <td>
                            <div class="d-flex gap-3">
                                <a href="#" class="text-primary" @click="getVoucherTransaction(voucher)">
                                    <i class="fas fa-credit-card text-primary"></i>
                                </a>
                                <a href="#" class="text-info" @click="printSingleVoucher(voucher)">
                                    <i class="fas fa-print text-info"></i>
                                </a>
                                <a href="#" class="text-danger" @click="deleteVoucher(voucher)">
                                    <i class="fas fa-trash-alt text-danger"></i>
                                </a>
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
                        <div class="modal-body">
                            <div class="alert alert-danger" v-if="!state.voucherTransaction">
                                No transaction details available, Add a voucher transaction using the form below.
                                <hr />
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Phone Number</th>
                                            <th>Amount</th>
                                            <th>Currency</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>
                                                <input type="text" v-model="state.transactionForm.phone_number"
                                                    class="form-control input-rounded" placeholder="+2567XXXXXXXX" />
                                            </td>
                                            <td>
                                                <input type="number" v-model="state.transactionForm.amount"
                                                    class="form-control input-rounded" placeholder="Amount in UGX" />
                                            </td>
                                            <td>
                                                <select v-model="state.transactionForm.currency"
                                                    class="form-select input-rounded">
                                                    <option value="UGX">UGX</option>
                                                    <option value="USD">USD</option>
                                                    <option value="EUR">EUR</option>
                                                </select>
                                            </td>
                                            <td>
                                                <select v-model="state.transactionForm.status"
                                                    class="form-select input-rounded">
                                                    <option value="successful">Successful</option>
                                                    <option value="failed">Failed</option>
                                                    <option value="pending">Pending</option>
                                                </select>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                                <div class="d-flex justify-content-end">
                                    <button class="btn btn-primary" @click="saveTransaction" :disabled="state.loading">
                                        <i class="fas fa-save"></i> Save Transaction
                                    </button>
                                </div>
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

            <!-- Create Voucher Modal -->
            <div v-if="state.showCreateModal" class="modal fade show d-block" tabindex="-1"
                style="background:rgba(0,0,0,0.3)">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Create Vouchers</h5>
                            <button type="button" class="btn-close" @click="state.showCreateModal = false"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="package_id" class="form-label">Select Data Plan</label>
                                    <select v-model="state.form.package_id" class="form-select input-rounded">
                                        <option value="" disabled> -- select a plan --</option>
                                        <option v-for="pkg in state.packages" :key="pkg.id" :value="pkg.id">{{ pkg.name
                                            }} - {{ pkg.formatted_price }}</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="quantity" class="form-label">Number of Vouchers</label>
                                    <input type="number" v-model="state.form.quantity" min="1"
                                        class="form-control input-rounded" />
                                </div>
                            </div>

                            <div class="d-flex justify-content-end">
                                <button class="btn btn-primary" @click="generateVouchers" :disabled="state.loading">
                                    <i class="fas fa-plus"></i> Generate Vouchers
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>