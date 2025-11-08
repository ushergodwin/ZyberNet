<script setup lang="ts">
import { ref, reactive, onMounted, onUnmounted, watch } from "vue";
import { Head, usePage } from "@inertiajs/vue3";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import { hasPermission } from "@/mixins/helpers.mixin.js";
import axios from "axios";
import emitter from '@/eventBus';
import UserSettings from "./UserSettings.vue";
import SupportContact from "./SupportContact.vue";
import Permissions from "./Permissions.vue";
import TransactionCharges from "./TransactionCharges.vue";
defineOptions({ layout: AdminLayout });

const state = reactive({
    currentUser: null,
});

onMounted(() => {
    const token = usePage().props.auth.user.api_token;

    if (token) {
        axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
    }
    state.currentUser = usePage().props.auth.user;
});
</script>

<template>
    <section class="container-fluid">

        <Head title="System Settings" />
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="h3">System Settings</h4>
        </div>
        <ul class="nav nav-tabs border-bottom">
            <li class="nav-item" v-if="hasPermission('view_users', state.currentUser?.permissions_list)">
                <a href="#users" class="nav-link active" data-bs-toggle="tab">Users</a>
            </li>
            <li class="nav-item">
                <a href="#support-contact" class="nav-link" data-bs-toggle="tab"
                    v-if="hasPermission('view_support_contacts', state.currentUser?.permissions_list)">Support
                    Contacts</a>
            </li>
            <li class="nav-item">
                <a href="#permissions" class="nav-link" data-bs-toggle="tab"
                    v-if="hasPermission('view_permissions', state.currentUser?.permissions_list)">Permissions</a>
            </li>
            <li class="nav-item">
                <a href="#transaction-charges" class="nav-link" data-bs-toggle="tab"
                    v-if="hasPermission('view_transaction_charges', state.currentUser?.permissions_list)">Transaction
                    Charges</a>
            </li>
        </ul>

        <div class="tab-content">
            <div class="tab-pane fade show active" id="users"
                v-if="hasPermission('view_users', state.currentUser?.permissions_list)">
                <UserSettings />
            </div>
            <div class="tab-pane fade" id="support-contact">
                <SupportContact v-if="hasPermission('view_support_contacts', state.currentUser?.permissions_list)" />
            </div>
            <div class="tab-pane fade" id="permissions">
                <Permissions v-if="hasPermission('view_permissions', state.currentUser?.permissions_list)" />
            </div>
            <div class="tab-pane fade" id="transaction-charges">
                <TransactionCharges
                    v-if="hasPermission('view_transaction_charges', state.currentUser?.permissions_list)" />
            </div>
        </div>
    </section>
</template>