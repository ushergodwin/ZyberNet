<script setup lang="ts">
import { ref, reactive, onMounted, onUnmounted, watch } from "vue";
import { Head, usePage } from "@inertiajs/vue3";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import { showLoader, hideLoader, swalNotification, swalConfirm, formatDate, hasPermission } from "@/mixins/helpers.mixin.js";
import axios from "axios";
import emitter from "@/eventBus";
defineOptions({ layout: AdminLayout });

const state = reactive({
    supportContacts: [],
    loading: false,
    error: null,
    searchQuery: '',
    routers: [],
    selectedRouter: null,
    selectedRouterId: 1,
    form: {
        type: '',
        phone_number: '',
        email: '',
        router_id: null,
        id: null
    },
    showAddContactModal: false,
    editSupportContact: false,
    currentUser: null,
});

const loadSupportContacts = () => {
    if (state.loading) return;
    state.loading = true;
    let url = `/api/configuration/support-contacts`;
    if (state.searchQuery && state.selectedRouterId) {
        url += `?search=${encodeURIComponent(state.searchQuery)}&router_id=${state.selectedRouterId}`;
    }
    if (state.selectedRouterId && state.searchQuery) {
        url += `?router_id=${state.selectedRouterId}&search=${encodeURIComponent(state.searchQuery)}`;
    }
    else if (state.selectedRouterId) {
        url += `?router_id=${state.selectedRouterId}`;
    } else if (state.searchQuery) {
        url += `?search=${encodeURIComponent(state.searchQuery)}`;
    }
    axios.get(url)
        .then(response => {
            state.supportContacts = response.data;
            if (state.supportContacts.length === 0) {
                state.error = "No more router support available.";
            }
            state.loading = false;
        })
        .catch(error => {
            state.error = "Failed to load support contacts.";
            console.error(error);
            state.loading = false;
        });
};
const handleSearch = (query: string) => {
    state.searchQuery = query;
    loadSupportContacts();
};

// load routers
const loadRouters = async () => {
    try {
        const response = await axios.get('/api/configuration/routers?no_paging=true');
        state.routers = response.data;
        if (state.routers.length > 0) {
            state.selectedRouter = state.routers[0];
            state.selectedRouterId = state.routers[0].id;
            state.form.router_id = state.selectedRouterId;
        }
    } catch (error) {
        console.error('Failed to load support contacts:', error);
    }
};

// add new support contact
const addSupportContact = () => {
    if (!state.form.type || !state.form.phone_number) {
        swalNotification('error', 'Please fill in all required fields.');
        return;
    }
    showLoader();
    axios.post('/api/configuration/support-contacts', state.form)
        .then(response => {
            hideLoader();
            swalNotification('success', state.editSupportContact ? 'Support contact updated successfully.' : 'Support contact added successfully.');
            loadSupportContacts();
            state.showAddContactModal = false;
            state.form = { type: '', phone_number: '', email: '', router_id: null, id: null };
        })
        .catch(error => {
            hideLoader();
            console.error('Failed to add support contact:', error);
            swalNotification('error', 'Failed to add support contact.');
        });
};

// edit support contact
const editSupportContact = (contact) => {
    state.editSupportContact = true;
    state.form = { ...contact };
    state.showAddContactModal = true;
};
// deleteSupportContact
const deleteSupportContact = (contact) => {
    swalConfirm.fire({
        title: 'Are you sure?',
        text: `Do you really want to delete the support contact for ${contact.type} - ${contact.phone_number}?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'No, cancel!',
        reverseButtons: true,
    })
        .then((result) => {
            if (result.isConfirmed) {
                showLoader();
                axios.delete(`/api/configuration/support-contacts/${contact.id}`)
                    .then(response => {
                        hideLoader();
                        swalNotification('success', 'Support contact deleted successfully.');
                        loadSupportContacts();
                    })
                    .catch(error => {
                        hideLoader();
                        console.error('Failed to delete support contact:', error);
                        swalNotification('error', 'Failed to delete support contact.');
                    });
            }
        });
};
// watch for selectedRouterId changes
watch(() => state.selectedRouterId, (newId) => {
    state.selectedRouter = state.routers.find(router => router.id === newId);
    if (newId || newId === 0) {
        loadSupportContacts();
    }
    if (newId) {
        state.form.router_id = newId;
    } else {
        state.form.router_id = null;
    }
});
onMounted(() => {
    const token = usePage().props.auth.user.api_token;

    if (token) {
        axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
    }
    state.currentUser = usePage().props.auth.user;
    loadSupportContacts();
    loadRouters();
    emitter.on('search', handleSearch);
});
onUnmounted(() => {
    emitter.off('search', handleSearch);
});
</script>

<template>
    <section class="container-fluid">

        <Head title="Support Contacts" />
        <div class="d-flex justify-content-end align-items-center mt-3 mb-3">
            <!-- Router selection -->
            <section>
                <div class="d-flex gap-3">
                    <select class="form-select w-auto" v-model="state.selectedRouterId">
                        <option :value="0">All Routers</option>
                        <option v-for="router in state.routers" :key="router.id" :value="router.id">
                            {{ router.name }}
                        </option>
                    </select>
                    <!-- add new support contact-->
                    <button class="btn btn-primary" @click="state.showAddContactModal = true"
                        v-if="hasPermission('create_support_contacts', state.currentUser?.permissions_list)">
                        <i class="fas fa-plus me-2"></i> Add Support Contact
                    </button>
                </div>
            </section>
        </div>
        <!-- Your page content here -->
        <div class="card card-body shadow">
            <table class="table table-striped table-hover" v-if="state.supportContacts.length">
                <thead>
                    <tr>
                        <th>Contact Type</th>
                        <th>Phone Number</th>
                        <th>Email</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="contact in state.supportContacts" :key="contact.id">
                        <td>{{ contact.type }}</td>
                        <td>
                            {{ contact.phone_number }}
                        </td>
                        <td>{{ contact.email || 'N/A' }}</td>
                        <td class="text-end">
                            <div class="d-flex gap-3">
                                <a href="#" class="text-primary" @click="editSupportContact(contact)"
                                    v-if="hasPermission('edit_support_contacts', state.currentUser?.permissions_list)">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="#" class="text-danger" @click="deleteSupportContact(contact)"
                                    v-if="hasPermission('edit_support_contacts', state.currentUser?.permissions_list)">
                                    <i class="fas fa-trash-alt"></i>
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
            <div v-if="!state.supportContacts.length" class="text-danger text-center my-3">
                <i class="fas fa-exclamation-triangle"></i> {{ state.error || "No support contacts found." }}
            </div>

            <!-- Add support contact model with form-->
            <div v-if="state.showAddContactModal" class="modal fade show d-block" tabindex="-1"
                style="background:rgba(0,0,0,0.3)">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                {{ state.editSupportContact ? "Edit Support Contact" : "Add New Support Contact" }}
                            </h5>
                            <button type="button" class="btn-close" @click="state.showAddContactModal = false"></button>
                        </div>
                        <form @submit.prevent="addSupportContact">
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label for="type" class="form-label">Contact Type</label>
                                    <select class="form-select" id="type" v-model="state.form.type" required>
                                        <option value="" disabled selected>Select Contact Type</option>
                                        <option value="Tel">Tel</option>
                                        <option value="WhatsApp">WhatsApp</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="phone_number" class="form-label">Phone Number</label>
                                    <input type="text" id="phone_number" v-model="state.form.phone_number"
                                        class="form-control" required />
                                </div>
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email (optional)</label>
                                    <input type="email" id="email" v-model="state.form.email" class="form-control" />
                                </div>
                                <div class="mb-3">
                                    <label for="router_id" class="form-label">Router</label>
                                    <select id="router_id" v-model="state.form.router_id" class="form-select">
                                        <option :value="null">All Routers</option>
                                        <option v-for="router in state.routers" :key="router.id" :value="router.id">
                                            {{ router.name }}
                                        </option>
                                    </select>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary"
                                    @click="state.showAddContactModal = false">Cancel</button>
                                <button type="submit" class="btn btn-primary">
                                    {{ state.editSupportContact ? "Update Contact" : "Save Contact"
                                    }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

    </section>
</template>