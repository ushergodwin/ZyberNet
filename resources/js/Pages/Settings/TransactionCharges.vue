<script setup lang="ts">
import { ref, reactive, onMounted, onUnmounted, watch } from "vue";
import { usePage } from "@inertiajs/vue3";
import { showLoader, hideLoader, swalNotification, swalConfirm, hasPermission } from "@/mixins/helpers.mixin.js";
import axios from "axios";
import emitter from "@/eventBus";

const state = reactive({
    transactionCharges: [],
    loading: false,
    error: null,
    searchQuery: '',
    selectedNetwork: '',
    form: {
        id: null,
        min_amount: '',
        max_amount: '',
        charge: '',
        network: '',
    },
    showModal: false,
    editMode: false,
    currentUser: null,
});

const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-UG', {
        style: 'currency',
        currency: 'UGX',
        minimumFractionDigits: 0
    }).format(amount);
};

const loadTransactionCharges = () => {
    if (state.loading) return;
    state.loading = true;
    state.error = null;

    let url = `/api/configuration/transaction-charges`;
    const params = [];

    if (state.searchQuery) {
        params.push(`search=${encodeURIComponent(state.searchQuery)}`);
    }
    if (state.selectedNetwork) {
        params.push(`network=${state.selectedNetwork}`);
    }

    if (params.length > 0) {
        url += `?${params.join('&')}`;
    }

    axios.get(url)
        .then(response => {
            state.transactionCharges = response.data;
            if (state.transactionCharges.length === 0) {
                state.error = "No transaction charges found.";
            }
            state.loading = false;
        })
        .catch(error => {
            state.error = "Failed to load transaction charges.";
            console.error(error);
            state.loading = false;
        });
};

const handleSearch = (query: string) => {
    state.searchQuery = query;
    loadTransactionCharges();
};

const openCreateModal = () => {
    state.editMode = false;
    state.form = {
        id: null,
        min_amount: '',
        max_amount: '',
        charge: '',
        network: 'MTN',
    };
    state.showModal = true;
};

const editTransactionCharge = (charge) => {
    state.editMode = true;
    state.form = { ...charge };
    state.showModal = true;
};

const saveTransactionCharge = () => {
    if (!state.form.min_amount || !state.form.max_amount || !state.form.charge || !state.form.network) {
        swalNotification('error', 'Please fill in all required fields.');
        return;
    }

    if (parseFloat(state.form.max_amount) <= parseFloat(state.form.min_amount)) {
        swalNotification('error', 'Maximum amount must be greater than minimum amount.');
        return;
    }

    showLoader();

    const requestData = {
        min_amount: parseInt(state.form.min_amount),
        max_amount: parseInt(state.form.max_amount),
        charge: parseFloat(state.form.charge),
        network: state.form.network,
    };

    const request = state.editMode
        ? axios.put(`/api/configuration/transaction-charges/${state.form.id}`, requestData)
        : axios.post('/api/configuration/transaction-charges', requestData);

    request
        .then(response => {
            hideLoader();
            swalNotification('success', state.editMode ? 'Transaction charge updated successfully.' : 'Transaction charge created successfully.');
            loadTransactionCharges();
            state.showModal = false;
        })
        .catch(error => {
            hideLoader();
            console.error('Failed to save transaction charge:', error);
            const message = error.response?.data?.message || 'Failed to save transaction charge.';
            swalNotification('error', message);
        });
};

const deleteTransactionCharge = (charge) => {
    swalConfirm.fire({
        title: 'Are you sure?',
        text: `Do you really want to delete the charge configuration for ${charge.network} (${formatCurrency(charge.min_amount)} - ${formatCurrency(charge.max_amount)})?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'No, cancel!',
        reverseButtons: true,
    })
        .then((result) => {
            if (result.isConfirmed) {
                showLoader();
                axios.delete(`/api/configuration/transaction-charges/${charge.id}`)
                    .then(response => {
                        hideLoader();
                        swalNotification('success', 'Transaction charge deleted successfully.');
                        loadTransactionCharges();
                    })
                    .catch(error => {
                        hideLoader();
                        console.error('Failed to delete transaction charge:', error);
                        swalNotification('error', 'Failed to delete transaction charge.');
                    });
            }
        });
};

watch(() => state.selectedNetwork, (newNetwork) => {
    loadTransactionCharges();
});

onMounted(() => {
    const token = usePage().props.auth.user.api_token;
    if (token) {
        axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
    }
    state.currentUser = usePage().props.auth.user;
    loadTransactionCharges();
    emitter.on('search', handleSearch);
});

onUnmounted(() => {
    emitter.off('search', handleSearch);
});
</script>

<template>
    <section class="container-fluid">
        <div class="d-flex justify-content-end align-items-center mt-3 mb-3">
            <section>
                <div class="d-flex gap-3">
                    <select class="form-select w-auto" v-model="state.selectedNetwork" :disabled="state.loading">
                        <option value="">All Networks</option>
                        <option value="MTN">MTN</option>
                        <option value="AIRTEL">AIRTEL</option>
                    </select>
                    <button class="btn btn-primary" @click="openCreateModal"
                        v-if="hasPermission('create_transaction_charges', state.currentUser?.permissions_list)">
                        <i class="fas fa-plus me-2"></i> Add Transaction Charge
                    </button>
                </div>
            </section>
        </div>

        <div class="card card-body shadow position-relative">
            <!-- Loading overlay -->
            <div v-if="state.loading"
                class="position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center"
                style="background: rgba(255,255,255,0.65); z-index: 10;">
                <span class="spinner-border text-primary"></span>
            </div>
            <table class="table table-striped table-hover" v-if="state.transactionCharges.length">
                <thead>
                    <tr>
                        <th>Network</th>
                        <th>Min Amount</th>
                        <th>Max Amount</th>
                        <th>Charge</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="charge in state.transactionCharges" :key="charge.id">
                        <td>
                            <span :class="charge.network === 'MTN' ? 'badge bg-warning text-dark' : 'badge bg-danger'">
                                {{ charge.network }}
                            </span>
                        </td>
                        <td>{{ formatCurrency(charge.min_amount) }}</td>
                        <td>{{ formatCurrency(charge.max_amount) }}</td>
                        <td>{{ formatCurrency(charge.charge) }}</td>
                        <td class="text-end">
                            <div class="d-flex gap-3">
                                <a href="#" class="text-primary" @click.prevent="editTransactionCharge(charge)"
                                    v-if="hasPermission('edit_transaction_charges', state.currentUser?.permissions_list)">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="#" class="text-danger" @click.prevent="deleteTransactionCharge(charge)"
                                    v-if="hasPermission('delete_transaction_charges', state.currentUser?.permissions_list)">
                                    <i class="fas fa-trash-alt"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div v-if="!state.transactionCharges.length && !state.loading" class="text-danger text-center my-3">
                <i class="fas fa-exclamation-triangle"></i> {{ state.error || "No transaction charges found." }}
            </div>

            <!-- Modal for Add/Edit -->
            <div v-if="state.showModal" class="modal fade show d-block" tabindex="-1"
                style="background:rgba(0,0,0,0.3)">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                {{ state.editMode ? "Edit Transaction Charge" : "Add New Transaction Charge" }}
                            </h5>
                            <button type="button" class="btn-close" @click="state.showModal = false"></button>
                        </div>
                        <form @submit.prevent="saveTransactionCharge">
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label for="network" class="form-label">Network *</label>
                                    <select class="form-select" id="network" v-model="state.form.network" required>
                                        <option value="">Select Network</option>
                                        <option value="MTN">MTN</option>
                                        <option value="AIRTEL">AIRTEL</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="min_amount" class="form-label">Minimum Amount (UGX) *</label>
                                    <input type="number" id="min_amount" v-model="state.form.min_amount"
                                        class="form-control" min="0" step="1" required />
                                    <small class="text-muted">Example: 500</small>
                                </div>
                                <div class="mb-3">
                                    <label for="max_amount" class="form-label">Maximum Amount (UGX) *</label>
                                    <input type="number" id="max_amount" v-model="state.form.max_amount"
                                        class="form-control" min="0" step="1" required />
                                    <small class="text-muted">Example: 5000</small>
                                </div>
                                <div class="mb-3">
                                    <label for="charge" class="form-label">Charge Amount (UGX) *</label>
                                    <input type="number" id="charge" v-model="state.form.charge" class="form-control"
                                        min="0" step="0.01" required />
                                    <small class="text-muted">Example: 100</small>
                                </div>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <small>
                                        This charge will be applied to all transactions within the specified amount
                                        range for the selected network.
                                    </small>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary"
                                    @click="state.showModal = false">Cancel</button>
                                <button type="submit" class="btn btn-primary">
                                    {{ state.editMode ? "Update Charge" : "Save Charge" }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>