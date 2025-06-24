<script setup lang="ts">
import { ref, reactive, onMounted } from "vue";
import { Head, usePage } from "@inertiajs/vue3";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import { showLoader, hideLoader, swalNotification, swalConfirm } from "@/mixins/helpers.mixin.js";
import axios from "axios";
defineOptions({ layout: AdminLayout });
const state = reactive({
    vouchersPackages: [],
    loading: false,
    showModal: false,
    isEdit: false,
    form: {
        name: "",
        price: 0,
        duration_minutes: 0,
        speed_limit: 0,
        id: 0,
        duration_hours: 0,
        is_active: true,
        profile: ""
    }
});
onMounted(() => {
    const token = usePage().props.auth.user.api_token;

    if (token) {
        axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
    }
    loadVoucherPackages();
});

const submitForm = () => {
    showLoader();
    const url = state.isEdit ? `/api/configuration/vouchers/packages/${state.form.id}` : '/api/configuration/vouchers/packages';
    const method = state.isEdit ? 'put' : 'post';
    // Ensure duration_minutes is calculated correctly
    state.form.duration_minutes = state.form.duration_hours * 60;
    axios[method](url, state.form)
        .then((res) => {
            hideLoader();
            swalNotification('success', res.data.message);
            state.showModal = false;
            // prepend or update the vouchersPackages list
            if (state.isEdit) {
                const index = state.vouchersPackages.findIndex(pkg => pkg.id === state.form.id);
                if (index !== -1) {
                    state.vouchersPackages[index] = res.data.package;
                }
            } else {
                state.vouchersPackages.unshift(res.data.package);
            }
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
        state.form = { ...voucherPackage };
    } else {
        state.form = {
            name: "",
            price: 0,
            duration_minutes: 0,
            speed_limit: 0,
            id: 0,
            duration_hours: 0,
            is_active: true,
            profile: ""
        };
    }
};

const loadVoucherPackages = () => {
    // pagination and loading state
    state.loading = true;
    axios.get('/api/configuration/vouchers/packages')
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
</script>

<template>
    <section class="container-fluid">

        <Head title="Voucher Packages" />
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="h3">Voucher Packages</h4>
            <button class="btn btn-primary btn-sm" @click="openModal(false)">
                <i class="fas fa-plus"></i> Create Voucher Package
            </button>
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
                        <th>Duration</th>
                        <th>Speed Limit (Mbps)</th>
                        <th>Profile</th>
                        <th style="width: 120px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="pkg in state.vouchersPackages" :key="pkg.id">
                        <td>{{ pkg.name }}</td>
                        <td>{{ pkg.formatted_price }}</td>
                        <td>{{ pkg.duration_hours }} {{ pkg.duration_hours > 1 ? `hrs` : `hr` }}</td>
                        <td>{{ pkg.speed_limit || 'Unlimited' }}</td>
                        <td>
                            <span class="badge bg-secondary">{{ pkg.profile || 'default' }}</span>
                        </td>
                        <td>
                            <div class="d-flex gap-3">
                                <a href="#" class="text-primary" @click="openModal(true, pkg)">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="#" class="text-danger" @click="deletePackage(pkg.id)">
                                    <i class="fas fa-trash"></i>
                                </a>
                                <!-- Deactivate package-->
                                <div class="form-check form-switch">
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
                <p>No voucher packages available.</p>
            </div>
        </div>
        <!-- Add/Edit Modal -->
        <div v-if="state.showModal" class="modal fade show d-block" tabindex="-1" style="background:rgba(0,0,0,0.3)">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ state.isEdit ? "Edit Voucher Package" : "Add Voucher Package" }}</h5>
                        <button type="button" class="btn-close" @click="state.showModal = false"></button>
                    </div>
                    <form @submit.prevent="submitForm">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="name" class="form-label">Name</label>
                                <input type="text" id="name" v-model="state.form.name" class="form-control"
                                    autocomplete="off" required>
                            </div>
                            <div class="mb-3">
                                <label for="price" class="form-label">Price</label>
                                <input type="number" id="price" v-model.number="state.form.price" autocomplete="off"
                                    class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label for="duration_hours" class="form-label">Duration (hours)</label>
                                <input type="number" id="duration_hours" v-model.number="state.form.duration_hours"
                                    class="form-control" autocomplete="off" required>
                            </div>
                            <div class="mb-3">
                                <label for="profile" class="form-label">Profile</label>
                                <input type="text" id="profile" v-model="state.form.profile" class="form-control"
                                    autocomplete="off">
                                <span class="form-text">
                                    The entered profile must match a profile configured in the Mikrotik router.
                                </span>
                            </div>
                            <div class="mb-3">
                                <label for="speed_limit" class="form-label">Speed Limit (Mbps)</label>
                                <input type="number" id="speed_limit" v-model.number="state.form.speed_limit"
                                    class="form-control" autocomplete="off">
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