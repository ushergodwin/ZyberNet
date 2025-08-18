<script setup lang="ts">
import { ref, reactive, onMounted } from "vue";
import { Head, usePage } from "@inertiajs/vue3";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import { showLoader, hideLoader, swalNotification, swalConfirm, hasPermission } from "@/mixins/helpers.mixin.js";
import axios from "axios";
defineOptions({ layout: AdminLayout });
import { watch } from "vue";
const state = reactive({
    vouchersPackages: [],
    loading: false,
    showModal: false,
    isEdit: false,
    form: {
        price: 100,
        name: "",
        profile_name: "",
        rate_limit: 0,
        session_timeout: 1,
        limit_bytes_total: 0,
        shared_users: 1,
        description: "",
        rate_limit_unit: 'Mbps',
        limit_bytes_unit: 'MB', // Default unit
        id: 0,
        session_timeout_unit: 'hours', // Default unit for session timeout
        router_id: 1, // Default router ID
    },
    routers: [],
    selectedRouterId: 1, // For router selection
    currentUser: null,
});
onMounted(() => {
    const token = usePage().props.auth.user.api_token;

    if (token) {
        axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
    }
    state.currentUser = usePage().props.auth.user;
    loadVoucherPackages();
    getRouters();
});

const submitForm = () => {
    showLoader();
    const url = state.isEdit ? `/api/configuration/vouchers/packages/${state.form.id}` : '/api/configuration/vouchers/packages';
    const method = state.isEdit ? 'put' : 'post';
    // format session_timeout based on unit

    axios[method](url, state.form)
        .then((res) => {
            hideLoader();
            swalNotification('success', res.data.message || state.isEdit ? 'Package updated successfully.' : 'Package created successfully.')
                .then(() => {
                    state.showModal = false;
                    // Reload the voucher packages
                    loadVoucherPackages();
                });
        })
        .catch((err) => {
            hideLoader();
            swalNotification('error', err.response.data.message || 'An error occurred');
        });
};

const openModal = (edit = false, voucherPackage = null) => {
    state.showModal = true;
    state.isEdit = edit;
    if (edit && voucherPackage) {
        let rate_limit = voucherPackage.rate_limit || 0;
        if (rate_limit) {
            rate_limit = rate_limit.split('/');
            rate_limit = rate_limit.length > 0 ? rate_limit[0] : 0;
        }

        let limit_bytes_total = 0;
        if (limit_bytes_total) {
            limit_bytes_total = formatBytes(voucherPackage.limit_bytes_total);
        }

        let session_timeout = voucherPackage.session_timeout;
        if (session_timeout) {
            session_timeout = session_timeout.split('/');
            session_timeout = session_timeout.length > 0 ? session_timeout[0] : 1;
        } else {
            session_timeout = 1; // Default to 1 hour if not set
        }
        state.form = {
            rate_limit: voucherPackage.js_rate_limit || 0,
            session_timeout: voucherPackage.js_session_timeout || 0,
            limit_bytes_total: voucherPackage.js_limit_bytes_total || 0,
            shared_users: voucherPackage.shared_users || 1,
            rate_limit_unit: voucherPackage.rate_limit_unit || 'Mbps',
            limit_bytes_unit: voucherPackage.limit_bytes_unit || 'MB',
            session_timeout_unit: voucherPackage.session_timeout_unit || 'hours',
            id: voucherPackage.id,
            price: voucherPackage.price || 1000,
            name: voucherPackage.name || "",
            profile_name: voucherPackage.profile_name || "",
            description: voucherPackage.description || "",
            router_id: voucherPackage.router_id,
        };
        console.log("state.form", state.form);
    } else {
        state.form = {
            price: 1000,
            name: "",
            profile_name: "",
            rate_limit: 0,
            session_timeout: 1,
            limit_bytes_total: 0,
            shared_users: 1,
            description: "",
            rate_limit_unit: 'Mbps',
            limit_bytes_unit: 'MB', // Default unit
            id: 0, // Reset ID for new package
            session_timeout_unit: 'hours', // Default unit for session timeout
            router_id: state.selectedRouterId || 1, // Use selected router ID or default to 1
        };
    }
};

const loadVoucherPackages = () => {
    // pagination and loading state
    state.loading = true;
    axios.get(`/api/configuration/vouchers/packages?router_id=${state.selectedRouterId}`)
        .then((res) => {
            state.vouchersPackages = res.data.packages;
            state.loading = false;
        })
        .catch((err) => {
            swalNotification('error', err.response.data.message || 'Failed to load voucher packages.');
            state.loading = false;
        });
}

const deletePackage = (id) => {
    swalConfirm.fire({
        title: 'Delete Voucher Package',
        text: "You are about to delete this voucher package. This action cannot be undone.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'No, cancel!',
        reverseButtons: true,
    })
        .then((result) => {
            if (result.isConfirmed) {
                showLoader();
                axios.delete(`/api/configuration/vouchers/packages/${id}`)
                    .then((res) => {
                        hideLoader();
                        swalNotification('success', res.data.message);
                        state.vouchersPackages = state.vouchersPackages.filter(pkg => pkg.id !== id);
                    })
                    .catch((err) => {
                        hideLoader();
                        swalNotification('error', err.response.data.message || 'Failed to delete package.');
                    });
            }
        });
};

// activateOrDeactivatePackage
const activateOrDeactivatePackage = (event, packageId) => {
    swalConfirm.fire({
        title: event.target.checked ? 'Activate Package' : 'Deactivate Package',
        text: `Are you sure you want to ${event.target.checked ? 'activate' : 'deactivate'} this package?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, proceed',
        cancelButtonText: 'No, cancel',
        reverseButtons: true,
    })
        .then((result) => {
            if (result.isConfirmed) {
                showLoader();
                axios.post(`/api/configuration/vouchers/packages/${packageId}/toggle`, {
                    is_active: event.target.checked
                })
                    .then((res) => {
                        hideLoader();
                        swalNotification('success', res.data.message);
                        const pkg = state.vouchersPackages.find(p => p.id === packageId);
                        if (pkg) pkg.is_active = event.target.checked;
                    })
                    .catch((err) => {
                        hideLoader();
                        swalNotification('error', err.response.data.message || 'Failed to update package status.');
                    });
            } else {
                // Reset checkbox if user cancels
                event.target.checked = !event.target.checked;
            }
        });
};

// convert bytes to MB or GB
const formatBytes = (bytes) => {
    // if bytes are less than a gb, return in MB
    if (bytes < 1024 * 1024) {
        return (bytes / 1024).toFixed(2);
    }
    // if bytes are greater than a gb, return in GB
    if (bytes >= 1024 * 1024) {
        return (bytes / (1024 * 1024)).toFixed(2);
    }
    return bytes;
};

// get routers
const getRouters = () => {
    axios.get('/api/configuration/routers?no_paging=true')
        .then((res) => {
            state.routers = res.data;
        })
        .catch((err) => {
            swalNotification('error', err.response.data.message || 'Failed to load routers.');
        });
};

// watch for changes in router_id
watch(() => state.selectedRouterId, (newRouterId) => {
    if (newRouterId) {
        state.form.router_id = newRouterId;
    } else {
        state.form.router_id = null; // Reset if "All Routers" is selected
    }
    if (newRouterId || newRouterId === 0) {
        // fetch voucher packages for the selected router
        let url = `/api/configuration/vouchers/packages?router_id=${newRouterId}`;
        if (newRouterId === 0) {
            url = '/api/configuration/vouchers/packages';
        }
        axios.get(url)
            .then((res) => {
                state.vouchersPackages = res.data.packages;
            })
            .catch((err) => {
                swalNotification('error', err.response.data.message || 'Failed to load voucher packages for the selected router.');
            });
    }
});

</script>

<template>
    <section class="container-fluid">

        <Head title="Data Plans" />
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="h3">Data Plans</h4>
            <div>
                <section class="d-flex gap-3">
                    <!-- Router Selection -->
                    <select v-model="state.selectedRouterId" class="form-select w-auto">
                        <option :value="0">All Routers</option>
                        <option v-for="router in state.routers" :key="router.id" :value="router.id">
                            {{ router.name }}
                        </option>
                    </select>
                    <button class="btn btn-primary" @click="openModal(false)"
                        v-if="hasPermission('create_data_plans', state.currentUser?.permissions_list)">
                        <i class="fas fa-plus"></i> Add Data Plan
                    </button>
                </section>
            </div>
        </div>

        <!-- Your page content here -->
        <div class="card card-body shadow">
            <!-- List of vouchers will be displayed here -->
            <div v-if="state.loading" class="text-center py-5">
                <i class="fas fa-spinner fa-spin fa-2x"></i>
            </div>
            <table v-if="!state.loading && state.vouchersPackages.length"
                class="table table-light table-hover align-middle mb-0 w-100">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Price</th>
                        <th>Rate Limit (Mbps)</th>
                        <th>Session Timeout</th>
                        <th>Data Limit</th>
                        <th style="width: 120px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="pkg in state.vouchersPackages" :key="pkg.id">
                        <td>{{ pkg.name }}</td>
                        <td>{{ pkg.formatted_price }}</td>
                        <td>{{ pkg.rate_limit || 'Unlimited' }}</td>
                        <td>{{ pkg.session_timeout || 'Unlimited' }}</td>
                        <td>{{ pkg.limit_bytes_total ? pkg.formatted_limit_bytes_total : 'Unlimited' }}</td>
                        <td>
                            <div class="d-flex gap-3">
                                <a href="#" class="text-primary" @click="openModal(true, pkg)"
                                    v-if="hasPermission('edit_data_plans', state.currentUser?.permissions_list)">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="#" class="text-danger" @click="deletePackage(pkg.id)"
                                    v-if="hasPermission('delete_data_plans', state.currentUser?.permissions_list)">
                                    <i class="fas fa-trash-alt"></i>
                                </a>
                                <!-- Deactivate package-->
                                <div class="form-check form-switch"
                                    v-if="hasPermission('edit_data_plans', state.currentUser?.permissions_list)">
                                    <input class="form-check-input" type="checkbox"
                                        :id="`flexSwitchCheckChecked-${pkg.id}`" :checked="pkg.is_active"
                                        @change="e => activateOrDeactivatePackage(e, pkg.id)">
                                    <label class="form-check-label visually-hidden"
                                        :for="`flexSwitchCheckChecked-${pkg.id}`">
                                        {{ pkg.is_active ? 'Active' : 'Inactive' }}
                                    </label>
                                </div>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div v-else class="text-center py-5">
                <p>No Data Plans available.</p>
            </div>
        </div>
        <!-- Add/Edit Modal -->
        <div v-if="state.showModal" class="modal fade show d-block" tabindex="-1" style="background:rgba(0,0,0,0.3)">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ state.isEdit ? "Edit Data Plan" : "Add Data Plan" }}</h5>
                        <button type="button" class="btn-close" @click="state.showModal = false"></button>
                    </div>
                    <form @submit.prevent="submitForm">
                        <div class="modal-body">
                            <!-- Optional Router Selection -->
                            <div class="mb-3">
                                <label for="router_id" class="form-label sr-only">Router</label>
                                <select id="router_id" v-model="state.form.router_id" class="form-select">
                                    <option :value="null">Select Router</option>
                                    <option v-for="router in state.routers" :key="router.id" :value="router.id">
                                        {{ router.name }}
                                    </option>
                                </select>
                            </div>
                            <div class="row">
                                <div class="col-md-5 mb-3">
                                    <label for="name" class="form-label">Plan Name</label>
                                    <input type="text" id="name" v-model="state.form.name" class="form-control"
                                        autocomplete="off" placeholder="e.g. 6 Hours" required>
                                </div>
                                <div class="col-md-5 mb-3">
                                    <label for="profile" class="form-label">Profile</label>
                                    <input type="text" id="profile" v-model="state.form.profile_name"
                                        class="form-control" autocomplete="off" placeholder="e.g. 1hr">
                                </div>
                                <div class="col-md-2 mb-3">
                                    <label for="shared_users" class="form-label">Shared Users</label>
                                    <input type="number" id="shared_users" v-model.number="state.form.shared_users"
                                        class="form-control" autocomplete="off" readonly>
                                </div>
                            </div>
                            <div class="mb-3 w-50">
                                <label for="price" class="form-label">Price</label>
                                <input type="number" id="price" v-model.number="state.form.price" autocomplete="off"
                                    class="form-control" required>
                            </div>

                            <div class="mb-3">
                                <label for="speed_limit" class="form-label">Rate Limit (Mbps)</label>
                                <div class="row">
                                    <div class="col-md-6">
                                        <input type="number" id="speed_limit" v-model.number="state.form.rate_limit"
                                            class="form-control" autocomplete="off">
                                    </div>
                                    <div class="col-md-6">
                                        <select class="form-select" v-model="state.form.rate_limit_unit">
                                            <option value="Mbps">Mbps</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="session_timeout" class="form-label">Session Timeout</label>
                                    <input type="number" id="session_timeout"
                                        v-model.number="state.form.session_timeout" class="form-control"
                                        autocomplete="off">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="session_timeout_unit" class="form-label">Unit</label>
                                    <select class="form-select" v-model="state.form.session_timeout_unit">
                                        <option value="hours">Hours</option>
                                        <option value="days">Days</option>
                                    </select>
                                </div>
                            </div>
                            <!-- limit_bytes_total -->
                            <div class="mb-3">
                                <label for="data_limit" class="form-label">Data Limit</label>
                                <div class="row">
                                    <div class="col-md-6">
                                        <input type="number" id="data_limit"
                                            v-model.number="state.form.limit_bytes_total" class="form-control"
                                            autocomplete="off">
                                    </div>
                                    <div class="col-md-6">
                                        <select class="form-select" v-model="state.form.limit_bytes_unit">
                                            <option value="MB">MB</option>
                                            <option value="GB">GB</option>
                                        </select>
                                    </div>
                                </div>
                                <span class="form-text">
                                    Set to 0 for unlimited data.
                                </span>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary"
                                @click="state.showModal = false">Cancel</button>
                            <button type="submit" class="btn btn-primary">
                                {{ state.isEdit ? "Update Package" : "Save Package" }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>
</template>