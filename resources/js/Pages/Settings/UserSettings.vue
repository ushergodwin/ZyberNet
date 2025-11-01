<script setup lang="ts">
import { ref, reactive, onMounted, onUnmounted, watch } from "vue";
import { Head, usePage } from "@inertiajs/vue3";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import { showLoader, hideLoader, swalNotification, swalConfirm, formatDate, hasPermission } from "@/mixins/helpers.mixin.js";
import axios from "axios";
import emitter from '@/eventBus';
defineOptions({ layout: AdminLayout });

const state = reactive({
    users: [],
    loading: false,
    error: null,
    pagination: {
        current_page: 0,
        last_page: 0,
        per_page: 10,
        total: 0,
        from: 0,
        to: 0,
    },
    showModal: false,
    isEdit: false,
    form: {
        id: null,
        name: '',
        email: '',
        password: '',
        confirmPassword: ''
    },
    tab: 0,
    searchQuery: '',
    currentUser: null,
    passwordInputType: 'password'
});

const loadUsers = (page) => {
    if (state.loading) return;
    state.loading = true;
    let url = state.tab === 0 ? `/api/configuration/users?page=${page}` : `/api/configuration/users?deleted=true&page=${page}`;
    if (state.searchQuery.trim() !== '') {
        url += `&search=${encodeURIComponent(state.searchQuery)}`;
    }
    axios.get(url)
        .then(response => {
            const data = response.data;
            state.users = data.data;
            state.pagination = {
                current_page: data.current_page,
                last_page: data.last_page,
                per_page: data.per_page,
                total: data.total,
                from: data.from,
                to: data.to
            };
            state.loading = false;
        })
        .catch(error => {
            state.error = "Failed to load users.";
            console.error(error);
            state.loading = false;
        });
};

const handlePageChange = (page) => {
    if (page < 1 || page > state.pagination.last_page) return;
    loadUsers(page);
};

const openModal = (isEdit, user = null) => {
    state.isEdit = isEdit;
    state.showModal = true;
    if (isEdit && user) {
        state.form.id = user.id;
        state.form.name = user.name;
        state.form.email = user.email;
        state.form.password = '';
        state.form.confirmPassword = '';
    } else {
        state.form.id = null;
        state.form.name = '';
        state.form.email = '';
        state.form.password = '';
        state.form.confirmPassword = '';
    }
};

const submitForm = () => {
    const url = state.isEdit ? `/api/configuration/users/${state.form.id}` : '/api/configuration/users';
    const method = state.isEdit ? 'put' : 'post';
    const data = {
        name: state.form.name,
        email: state.form.email,
        password: state.form.password,
        confirmPassword: state.form.confirmPassword
    };

    if (!state.isEdit && (state.form.password !== state.form.confirmPassword)) {
        swalNotification('error', 'Passwords do not match');
        return;
    }
    if (!state.isEdit && !validatePassword(state.form.password)) {
        swalNotification('error', 'Password must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, one number, and one special character');
        return;
    }
    showLoader();
    axios[method](url, data)
        .then(response => {
            if (response.status === 200) {
                hideLoader();
                swalNotification('success', state.isEdit ? 'User updated successfully' : 'User created successfully');
                state.showModal = false;
                loadUsers(1);
            } else {
                hideLoader();
                swalNotification('error', response.data.error || 'Failed to save user');
            }
        })
        .catch(error => {
            hideLoader();
            console.error(error);
            let err = 'Failed to save user changes';
            if (error?.response?.data?.error) {
                err = error?.response?.data?.error;
            }
            swalNotification('error', err);
        });
}

// password validation
const validatePassword = (password) => {
    // At least 8 chars, 1 lowercase, 1 uppercase, 1 digit, 1 special char from a broader set
    const passwordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&#^_\-+=~])[A-Za-z\d@$!%*?&#^_\-+=~]{8,}$/;
    return passwordRegex.test(password);
};


const deleteUser = (user) => {
    swalConfirm.fire({
        title: 'Delete User',
        text: `Are you sure you want to delete ${user.name}'s account?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'No, cancel!',
        reverseButtons: true,
    }).then((result) => {
        if (result.isConfirmed) {
            showLoader();
            axios.delete(`/api/configuration/users/${user.id}`)
                .then(response => {
                    hideLoader();
                    if (response.status === 200) {
                        swalNotification('success', 'User deleted successfully');
                        loadUsers(state.pagination.current_page);
                    } else {
                        swalNotification('error', response.data.error || 'Failed to delete user');
                    }
                })
                .catch(error => {
                    hideLoader();
                    console.error(error);
                    swalNotification('error', 'Failed to delete user');
                });
        }
    })
}

// restore deleted user
const restoreUser = (user) => {
    swalConfirm.fire({
        title: 'Restore User',
        text: `Are you sure you want to restore ${user.name}'s account?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, restore it!',
        cancelButtonText: 'No, cancel!',
        reverseButtons: true,
    }).then((result) => {
        if (result.isConfirmed) {
            showLoader();
            axios.post(`/api/configuration/users/${user.id}/restore`)
                .then(response => {
                    hideLoader();
                    if (response.status === 200) {
                        swalNotification('success', 'User restored successfully');
                        loadUsers(state.pagination.current_page);
                    } else {
                        swalNotification('error', response.data.error || 'Failed to restore user');
                    }
                })
                .catch(error => {
                    hideLoader();
                    console.error(error);
                    swalNotification('error', 'Failed to restore user');
                });
        }
    })
};
const handleSearch = (value: string) => {
    state.searchQuery = value;
    if (value.trim() === '') {
        loadUsers(1);
        return;
    }
    loadUsers(1);
};

const togglePassword = () => {
    state.passwordInputType = state.passwordInputType === 'password' ? 'text' : 'password';
}
watch(() => state.tab, (newTab) => {
    loadUsers(1);
});



onMounted(() => {
    const token = usePage().props.auth.user.api_token;

    if (token) {
        axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
    }
    state.currentUser = usePage().props.auth.user;
    loadUsers(1);
    emitter.on('search', handleSearch);
});
onUnmounted(() => {
    emitter.off('search', handleSearch);
});
</script>

<template>
    <section class="container-fluid">

        <Head title="System Users" />
        <div class="d-flex justify-content-end align-items-center mt-1">
            <section>
                <div class="d-flex gap-3">
                    <button class="btn btn-primary btn-sm" @click="openModal(false)"
                        v-if="hasPermission('create_users', state.currentUser?.permissions_list)">
                        <i class="fas fa-plus"></i> Add New User
                    </button>

                    <!-- filter users, all or trashed-->
                    <select class="form-select form-select-sm w-auto" v-model="state.tab">
                        <option :value="0">All Users</option>
                        <option :value="1">Deleted Users</option>
                    </select>
                </div>
            </section>
        </div>
        <section class="mt-3 mb-3">
            <div class="card card-body shadow" v-if="state.tab == 0">
                <table class="table table-striped table-hover" v-if="state.users.length">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="user in state.users" :key="user.id">
                            <td>{{ user.name }}</td>
                            <td>{{ user.email }}</td>
                            <td>{{ formatDate(user.created_at) }}</td>
                            <td>
                                <div class="d-flex gap-3">
                                    <a href="#" class="text-primary" @click="openModal(true, user)"
                                        v-if="hasPermission('edit_users', state.currentUser?.permissions_list)">
                                        <i class="fas fa-edit text-primary"></i>
                                    </a>
                                    <a href="#" class="text-danger" @click="deleteUser(user)"
                                        v-if="hasPermission('delete_users', state.currentUser?.permissions_list)">
                                        <i class="fas fa-trash text-danger"></i>
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
                <div v-if="!state.users.length" class="text-danger text-center my-3">
                    <i class="fas fa-exclamation-triangle"></i> {{ state.error || "No users found." }}
                </div>
                <!-- build pages -->
                <div class="d-flex justify-content-end mt-3 gap-2" v-if="state.users.length">
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
            </div>

            <div class="card card-body shadow" v-if="state.tab == 1">
                <table class="table table-striped table-hover" v-if="state.users.length">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="user in state.users" :key="user.id">
                            <td>{{ user.name }}</td>
                            <td>{{ user.email }}</td>
                            <td>{{ formatDate(user.created_at) }}</td>
                            <td>
                                <div class="d-flex gap-3">
                                    <a href="#" class="text-danger" @click="restoreUser(user)">
                                        <i class="fas fa-trash-alt text-success"></i>
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
                <div v-if="!state.users.length" class="text-danger text-center my-3">
                    <i class="fas fa-exclamation-triangle"></i> {{ state.error || "No users found." }}
                </div>
                <!-- build pages -->
                <div class="d-flex justify-content-end mt-3 gap-2" v-if="state.users.length">
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
            </div>
        </section>
        <!-- Your page content here -->
        <!-- Add/Edit Modal -->
        <div v-if="state.showModal" class="modal fade show d-block" tabindex="-1" style="background:rgba(0,0,0,0.3)">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ state.isEdit ? "Edit User" : "Add New User" }}</h5>
                        <button type="button" class="btn-close" @click="state.showModal = false"></button>
                    </div>
                    <form @submit.prevent="submitForm">
                        <div class="modal-body">
                            <div class="row mb-3">
                                <div class="col-md-6 mb-3">
                                    <label for="name" class="form-label">Name</label>
                                    <input type="text" id="name" v-model="state.form.name" class="form-control"
                                        autocomplete="off" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" id="email" v-model="state.form.email" class="form-control"
                                        autocomplete="off" required>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-5 mb-3">
                                    <label for="password" class="form-label">Password</label>
                                    <input :type="state.passwordInputType" id="password" v-model="state.form.password"
                                        class="form-control" autocomplete="off" required>
                                </div>
                                <div class="col-md-5 mb-3">
                                    <label for="confirmPassword" class="form-label">Confirm Password</label>
                                    <input :type="state.passwordInputType" id="confirmPassword"
                                        v-model="state.form.confirmPassword" class="form-control" autocomplete="off"
                                        required>
                                </div>
                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="switchDefault"
                                            @change="togglePassword">
                                        <label class="form-check-label" for="switchDefault">
                                            {{
                                                state.passwordInputType === `password` ? `Show Password` : `Hide Password`
                                            }}
                                        </label>
                                    </div>
                                </div>
                                <em class="text-info">
                                    <i class="fas fa-info-circle"></i> &nbsp;
                                    Password must be at least 8 characters long and contain at least one
                                    uppercase
                                    letter,
                                    one lowercase letter, one number, and one special character.
                                </em>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary"
                                @click="state.showModal = false">Cancel</button>
                            <button type="submit" class="btn btn-primary">
                                {{ state.isEdit ? "Update User" : "Save User" }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>
</template>