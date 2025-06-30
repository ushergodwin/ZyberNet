<script setup lang="ts">
import { ref, reactive, onMounted } from "vue";
import { Head, usePage } from "@inertiajs/vue3";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import { showLoader, hideLoader, swalNotification, swalConfirm } from "@/mixins/helpers.mixin.js";
import axios from "axios";

defineOptions({ layout: AdminLayout });

/**
 * Router configuration type
 */
type RouterConfig = {
    id: number;
    name: string;
    host: string;
    port: number;
    username: string;
    password?: string;
};

type PaginatedRouters = {
    data: RouterConfig[];
    current_page: number;
    last_page: number;
};

const routers = ref<RouterConfig[]>([]);
const page = ref(1);
const lastPage = ref(1);
const loading = ref(false);
const hasMore = ref(true);
const showModal = ref(false);
const isEdit = ref(false);
const form = reactive<RouterConfig>({
    id: 0,
    name: "",
    host: "",
    port: 8728,
    username: "",
    password: "",
});

/**
 * Fetch routers with pagination (for infinite scroll)
 */
const fetchRouters = () => {
    if (loading.value || !hasMore.value) return;
    loading.value = true;
    axios.get(
        `/api/configuration/routers?page=${page.value}`)
        .then((res: any) => {
            const paginated: PaginatedRouters = res.data;
            routers.value.push(...paginated.data);
            lastPage.value = paginated.last_page;
            if (page.value >= lastPage.value) hasMore.value = false;
            else page.value++;
            loading.value = false;
        })
        .catch((err: any) => {
            swalNotification("error", "Failed to load routers.");
            loading.value = false;
        });
};

/**
 * Open modal for add or edit
 */
function openModal(edit = false, router?: RouterConfig) {
    isEdit.value = edit;
    if (edit && router) {
        Object.assign(form, router);
    } else {
        Object.assign(form, { id: 0, name: "", host: "", port: 8728, username: "", password: "" });
    }
    showModal.value = true;
}

/**
 * Submit add or edit
 */
function submitForm() {
    showLoader(isEdit.value ? "Updating..." : "Saving...");
    if (isEdit.value) {
        axios.post(
            `/api/configuration/routers/${form.id}?_method=PUT`,
            form)
            .then((res: any) => {
                hideLoader();
                swalNotification("success", "Router updated successfully");
                // Update in list
                const idx = routers.value.findIndex(r => r.id === form.id);
                if (idx !== -1) routers.value[idx] = { ...form };
                showModal.value = false;
            })
            .catch((err: any) => {
                hideLoader();
                swalNotification("error", err.response?.data?.message || "Failed to save router.");
            })
    } else {
        axios.post(
            "/api/configuration/routers",
            form)
            .then((res: any) => {
                hideLoader();
                swalNotification("success", "Router added successfully");
                routers.value.unshift(res.data.configuration);
                showModal.value = false;
            })
            .catch((err: any) => {
                hideLoader();
                swalNotification("error", err.response?.data?.message || "Failed to save router.");
            })
    }
}

/**
 * Delete router with confirmation
 */
async function deleteRouter(router: RouterConfig) {
    const result = await swalConfirm.fire({
        title: `Delete ${router.name}?`,
        text: "This action cannot be undone!",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Yes, delete it!",
        cancelButtonText: "Cancel",
        reverseButtons: true,
    });
    if (result.isConfirmed) {
        showLoader("Deleting...");
        axios.post(
            `/api/configuration/routers/${router.id}?_method=DELETE`,
            {},
        )
            .then((res: any) => {
                routers.value = routers.value.filter(r => r.id !== router.id);
                swalNotification("success", "Router deleted successfully");
                hideLoader();
            })
            .catch((err: any) => {
                swalNotification("error", err.response?.data?.message || "Failed to delete router.");
                hideLoader();
            });
    }
}

/**
 * Infinite scroll handler
 */
function onScroll(e: Event) {
    const el = e.target as HTMLElement;
    if (el.scrollTop + el.clientHeight >= el.scrollHeight - 10) {
        fetchRouters();
    }
}

// test router connection at /api/configuration/routers/{id}/test (POST)
function testRouterConnection(id: number) {
    showLoader("Testing connection...");
    axios.post(`/api/configuration/routers/${id}/test`)
        .then((res: any) => {
            if (res.status === 200) {
                swalNotification("success", "Router connection successful.");
            } else {
                swalNotification("error", res.data?.error || "Failed to connect to router.");
            }
        })
        .catch((err: any) => {
            swalNotification("error", "Failed to connect to router.");
        });
}
onMounted(() => {
    const token = usePage().props.auth.user.api_token;

    if (token) {
        axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
    }
    fetchRouters();
});
</script>

<template>
    <section class="container-fluid">

        <Head title="Router Configuration" />
        <div class="d-flex justify-content-between mt-2 py-1">
            <h4 class="fw-bold">Router Configurations</h4>
            <button class="btn btn-primary btn-sm" @click="openModal(false)">
                <i class="fas fa-plus me-2"></i> Add Router
            </button>
        </div>
        <div class="card card-body mt-1 shadow" @scroll="onScroll">
            <table class="table table-light table-hover align-middle mb-0 w-100">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Host</th>
                        <th>Port</th>
                        <th>Username</th>
                        <th style="width: 120px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="router in routers" :key="router.id">
                        <td>{{ router.name }}</td>
                        <td>{{ router.host }}</td>
                        <td>{{ router.port }}</td>
                        <td>{{ router.username }}</td>
                        <td>
                            <div class="d-flex gap-3">
                                <a href="#" class="text-primary" @click="openModal(true, router)">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="#" class="text-danger" @click="deleteRouter(router)">
                                    <i class="fas fa-trash"></i>
                                </a>
                                <!-- test connection-->
                                <a href="#" class="text-success" @click="testRouterConnection(router.id)">
                                    <i class="fas fa-plug"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="routers.length === 0 && !loading">
                        <td colspan="5" class="text-center text-muted">No routers found.</td>
                    </tr>
                </tbody>
            </table>
            <div v-if="loading" class="text-center py-3">
                <span class="spinner-border spinner-border-sm"></span> Loading...
            </div>
            <div v-if="!hasMore && routers.length > 0" class="text-center text-muted py-2">
                End of list
            </div>
        </div>

        <!-- Add/Edit Modal -->
        <div v-if="showModal" class="modal fade show d-block" tabindex="-1" style="background:rgba(0,0,0,0.3)">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ isEdit ? "Edit Router" : "Add Router" }}</h5>
                        <button type="button" class="btn-close" @click="showModal = false"></button>
                    </div>
                    <form @submit.prevent="submitForm">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Name</label>
                                <input v-model="form.name" type="text" class="form-control" required maxlength="100" />
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Host</label>
                                <input v-model="form.host" type="text" class="form-control" required maxlength="60" />
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Port</label>
                                <input v-model.number="form.port" type="number" class="form-control" required min="1" />
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Username</label>
                                <input v-model="form.username" type="text" class="form-control" required maxlength="100"
                                    autocomplete="off" />
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <input v-model="form.password" type="password" class="form-control"
                                    autocomplete="off" />
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" @click="showModal = false">Cancel</button>
                            <button type="submit" class="btn btn-primary">{{ isEdit ? "Update" : "Save" }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.table th,
.table td {
    vertical-align: middle;
}

.modal {
    display: block;
}

.bg-white {
    width: 100%;
}

@media (max-width: 991px) {
    .bg-white {
        padding: 0.5rem !important;
    }

    .table {
        font-size: 0.95rem;
    }
}
</style>
