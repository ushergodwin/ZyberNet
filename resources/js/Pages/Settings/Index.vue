<script setup lang="ts">
import { ref, reactive, onMounted, onUnmounted, watch } from "vue";
import { Head, usePage } from "@inertiajs/vue3";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import { showLoader, hideLoader, swalNotification, swalConfirm, formatDate } from "@/mixins/helpers.mixin.js";
import axios from "axios";
import emitter from '@/eventBus';
import UserSettings from "./UserSettings.vue";
import SupportContact from "./SupportContact.vue";
defineOptions({ layout: AdminLayout });

const state = reactive({
});



onMounted(() => {
    const token = usePage().props.auth.user.api_token;

    if (token) {
        axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
    }
});
</script>

<template>
    <section class="container-fluid">

        <Head title="System Users" />
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="h3">System Settings</h4>
        </div>
        <ul class="nav nav-tabs border-bottom">
            <li class="nav-item">
                <a href="#users" class="nav-link active" data-bs-toggle="tab">Users</a>
            </li>
            <li class="nav-item">
                <a href="#support-contact" class="nav-link" data-bs-toggle="tab">Support Contacts</a>
            </li>
        </ul>

        <div class="tab-content">
            <div class="tab-pane fade show active" id="users">
                <UserSettings />
            </div>
            <div class="tab-pane fade" id="support-contact">
                <SupportContact />
            </div>
        </div>
    </section>
</template>