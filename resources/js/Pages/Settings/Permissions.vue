<script setup lang="ts">
import { reactive, onMounted, watch } from "vue";
import axios from "axios";
import { swalNotification, showLoader, hideLoader, swalConfirm, hasPermission } from "@/mixins/helpers.mixin.js";
import { usePage } from "@inertiajs/vue3";

const state = reactive({
    roles: [],
    permissions: [],
    users: [],
    selectedRole: null,
    selectedPermissions: [],
    newRoleName: '',
    selectedUser: null,
    userRoles: [],
    currentUser: null,
});

// Load roles, permissions, users
const loadData = () => {
    showLoader();
    Promise.all([
        axios.get('/api/configuration/roles'),
        axios.get('/api/configuration/permissions'),
        axios.get('/api/configuration/users?no_paging=true')
    ]).then(([rolesRes, permsRes, usersRes]) => {
        state.roles = rolesRes.data;
        state.permissions = permsRes.data;
        state.users = usersRes.data;
        hideLoader();
    }).catch(err => {
        hideLoader();
        swalNotification('error', 'Failed to load data.');
        console.error(err);
    });
};

// Select a role to manage permissions
const selectRole = (role) => {
    state.selectedRole = role;
    state.selectedPermissions = role.permissions.map(p => p.name);
};

// Save permission assignments for selected role
const savePermissions = () => {
    if (!state.selectedRole) return;
    showLoader();
    axios.post(`/api/configuration/roles/${state.selectedRole.id}/assign-permissions`, {
        permissions: state.selectedPermissions
    }).then(res => {
        hideLoader();
        swalNotification('success', 'Permissions updated successfully.');
        selectRole(res.data.role);
    }).catch(err => {
        hideLoader();
        swalNotification('error', 'Failed to update permissions.');
    });
};

// Create a new role (permissions can be empty at creation)
const createRole = () => {
    if (!state.newRoleName.trim()) {
        swalNotification('error', 'Role name cannot be empty');
        return;
    }
    showLoader();
    axios.post('/api/configuration/roles', {
        name: state.newRoleName
    }).then(res => {
        hideLoader();
        swalNotification('success', 'Role created successfully');
        state.roles.push(res.data.role);
        state.selectedRole = res.data.role; // auto-select the new role for permissions
        state.newRoleName = '';
        state.selectedPermissions = [];
    }).catch(err => {
        hideLoader();
        swalNotification('error', 'Failed to create role');
    });
};

// Assign roles to user
const assignRolesToUser = () => {
    if (!state.selectedUser) {
        swalNotification('error', 'Select a user');
        return;
    }
    showLoader();
    axios.post(`/api/configuration/users/${state.selectedUser.id}/assign-roles`, {
        roles: state.userRoles
    }).then(res => {
        hideLoader();
        swalNotification('success', 'Roles assigned successfully');
    }).catch(err => {
        hideLoader();
        swalNotification('error', 'Failed to assign roles');
    });
};

// Watch selected user and pre-check their roles
watch(() => state.selectedUser, (user) => {
    if (user) {
        state.userRoles = user.roles ? user.roles.map(r => r.name) : [];
    } else {
        state.userRoles = [];
    }
});

onMounted(() => {
    const token = usePage().props.auth.user.api_token;
    if (token) axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
    state.currentUser = usePage().props.auth.user;
    loadData();
});

// Delete a role
const deleteRole = (role) => {
    if (!role) return;
    swalConfirm.fire({
        icon: 'warning',
        title: 'Confirm Delete',
        text: `Are you sure you want to delete the role "${role.name}"? This action cannot be undone.`,
        showCancelButton: true,
        reverseButtons: true,
        confirmButtonText: 'Yes, Delete',
    }).then((result) => {
        if (result.isConfirmed) {
            showLoader();
            axios.delete(`/api/configuration/roles/${role.id}`)
                .then(res => {
                    hideLoader();
                    swalNotification('success', res.data.message);
                    state.roles = state.roles.filter(r => r.id !== role.id);

                    if (state.selectedRole && state.selectedRole.id === role.id) {
                        state.selectedRole = null;
                        state.selectedPermissions = [];
                    }
                })
                .catch(err => {
                    hideLoader();
                    const message = err.response?.data?.message || 'Failed to delete role.';
                    swalNotification('error', message);
                    console.error(err);
                });
        }
    });
};

</script>

<template>
    <div class="row mt-1 mb-1">
        <!-- Left panel: Roles list + create role -->
        <div class="col-md-4">
            <div class="card card-body shadow mb-3"
                v-if="hasPermission('view_roles', state.currentUser?.permissions_list)">
                <h6>Roles</h6>
                <ul class="list-group mb-3">
                    <li v-for="role in state.roles" :key="role.id"
                        class="list-group-item d-flex justify-content-between align-items-center"
                        :class="{ active: state.selectedRole && state.selectedRole.id === role.id }">
                        <span @click="selectRole(role)" style="cursor:pointer">{{ role.name }}</span>
                        <button class="btn btn-sm btn-danger" :disabled="role.users_count && role.users_count > 0"
                            @click="deleteRole(role)"
                            v-if="hasPermission('delete_roles', state.currentUser?.permissions_list)">
                            Delete
                        </button>
                    </li>
                </ul>
                <div v-if="hasPermission('create_roles', state.currentUser?.permissions_list)">
                    <h6>Create New Role</h6>
                    <input type="text" v-model="state.newRoleName" class="form-control mb-2" placeholder="Role Name">
                    <button class="btn btn-success mt-2" @click="createRole">Create Role</button>
                </div>
            </div>
        </div>

        <!-- Middle panel: Selected role permissions -->
        <div class="col-md-4">
            <div class="card card-body shadow mb-3"
                v-if="hasPermission('assign_permissions', state.currentUser?.permissions_list)">
                <h6>Assign Permissions</h6>
                <div v-if="state.selectedRole">
                    <small>Role: {{ state.selectedRole.name }}</small>
                    <div class="form-check" v-for="perm in state.permissions" :key="perm.id">
                        <input type="checkbox" class="form-check-input" v-model="state.selectedPermissions"
                            :value="perm.name" :id="'perm-' + perm.name">
                        <label class="form-check-label" :for="'perm-' + perm.name">{{ perm.name }}</label>
                    </div>
                    <button class="btn btn-primary mt-2" @click="savePermissions">Save Permissions</button>
                </div>
                <div v-else class="text-muted">Select or create a role to assign permissions</div>
            </div>
        </div>

        <!-- Right panel: Assign roles to users -->
        <div class="col-md-4" v-if="hasPermission('assign_permissions', state.currentUser?.permissions_list)">
            <div class="card card-body shadow mb-3">
                <h6>Assign Roles to Users</h6>
                <select v-model="state.selectedUser" class="form-select mb-2">
                    <option disabled :value="null">Select User</option>
                    <option v-for="user in state.users" :key="user.id" :value="user">{{ user.name }} ({{ user.email }})
                    </option>
                </select>

                <div class="form-check" v-for="role in state.roles" :key="role.id">
                    <input type="checkbox" class="form-check-input" v-model="state.userRoles" :value="role.name"
                        :id="'user-role-' + role.id" :disabled="!role.permissions || role.permissions.length === 0">
                    <label class="form-check-label" :for="'user-role-' + role.id">
                        {{ role.name }}
                        <small v-if="!role.permissions || role.permissions.length === 0" class="text-muted">(Add
                            permissions first)</small>
                    </label>
                </div>

                <button class="btn btn-primary mt-2" @click="assignRolesToUser">Assign Roles</button>
            </div>
        </div>
    </div>
</template>
