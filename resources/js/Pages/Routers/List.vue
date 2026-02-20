<script setup lang="ts">
import { ref, reactive, onMounted } from "vue";
import { Head, usePage } from "@inertiajs/vue3";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import { showLoader, hideLoader, swalNotification, swalConfirm, hasPermission } from "@/mixins/helpers.mixin.js";
import axios from "axios";

defineOptions({ layout: AdminLayout });

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

type WireguardData = {
    peer_name: string;
    peer_ip: string;
    peer_private_key: string;
    peer_public_key: string;
    server_public_key: string;
    server_public_ip: string;
    server_port: number;
    mikrotik_listen_port: number;
    mikrotik_instructions: Record<string, string>;
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

// Wizard state
const wizardStep = ref(1);
const wireguardData = ref<WireguardData | null>(null);
const wgPeerName = ref("");
const wgPastedJson = ref("");
const wgLoading = ref(false);
const wgManualMode = ref(false);
const wgManualConfirmed = ref(false);

const state = reactive({
    currentUser: null as any,
});

const fetchRouters = () => {
    if (loading.value || !hasMore.value) return;
    loading.value = true;
    axios.get(`/api/configuration/routers?page=${page.value}`)
        .then((res: any) => {
            const paginated: PaginatedRouters = res.data;
            routers.value.push(...paginated.data);
            lastPage.value = paginated.last_page;
            if (page.value >= lastPage.value) hasMore.value = false;
            else page.value++;
            loading.value = false;
        })
        .catch((err: any) => {
            swalNotification("error", err.response?.data?.message || "Failed to load routers.");
            loading.value = false;
        });
};

function openModal(edit = false, router?: RouterConfig) {
    isEdit.value = edit;
    if (edit && router) {
        Object.assign(form, router);
    } else {
        Object.assign(form, { id: 0, name: "", host: "", port: 8728, username: "", password: "" });
        wizardStep.value = 1;
        wireguardData.value = null;
        wgPeerName.value = "";
        wgPastedJson.value = "";
        wgManualMode.value = false;
        wgManualConfirmed.value = false;
    }
    showModal.value = true;
}

function closeModal() {
    showModal.value = false;
    wizardStep.value = 1;
    wireguardData.value = null;
    wgManualMode.value = false;
    wgManualConfirmed.value = false;
}

/**
 * Step 1: Auto-create WireGuard peer via API
 */
function autoCreatePeer() {
    if (!wgPeerName.value.trim()) {
        swalNotification("error", "Please enter a router/peer name.");
        return;
    }
    wgLoading.value = true;
    axios.post("/api/configuration/wireguard/peers", { peer_name: wgPeerName.value.trim() })
        .then((res: any) => {
            wgLoading.value = false;
            if (res.data.error) {
                swalNotification("error", res.data.error);
                return;
            }
            wireguardData.value = res.data;
            prefillFormFromWireguard(res.data);
            wizardStep.value = 2;
        })
        .catch((err: any) => {
            wgLoading.value = false;
            const msg = err.response?.data?.error || err.response?.data?.message || "Failed to create WireGuard peer. Try the manual setup instead.";
            swalNotification("error", msg);
        });
}

/**
 * Step 1: Parse manually pasted WireGuard JSON
 */
function parseManualOutput() {
    if (!wgPastedJson.value.trim()) {
        swalNotification("error", "Please paste the JSON output from the WireGuard script.");
        return;
    }
    wgLoading.value = true;
    axios.post("/api/configuration/wireguard/parse", { output: wgPastedJson.value.trim() })
        .then((res: any) => {
            wgLoading.value = false;
            wireguardData.value = res.data;
            prefillFormFromWireguard(res.data);
            wizardStep.value = 2;
        })
        .catch((err: any) => {
            wgLoading.value = false;
            swalNotification("error", err.response?.data?.error || "Invalid JSON. Please paste the complete output.");
        });
}

/**
 * Skip WireGuard setup (direct connection)
 */
function skipWireguard() {
    wireguardData.value = null;
    wizardStep.value = 3;
}

/**
 * Pre-fill router form from WireGuard data
 */
function prefillFormFromWireguard(data: WireguardData) {
    form.name = data.peer_name;
    form.host = data.peer_ip;
    form.port = 8728;
    form.username = "";
    form.password = "";
}

/**
 * Copy text to clipboard
 */
async function copyText(text: string) {
    try {
        await navigator.clipboard.writeText(text);
        swalNotification("success", "Copied to clipboard");
    } catch {
        // Fallback for older browsers
        const ta = document.createElement("textarea");
        ta.value = text;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand("copy");
        document.body.removeChild(ta);
        swalNotification("success", "Copied to clipboard");
    }
}

/**
 * Copy all MikroTik commands at once
 */
function copyAllCommands() {
    if (!wireguardData.value?.mikrotik_instructions) return;
    const commands = Object.values(wireguardData.value.mikrotik_instructions).join("\n");
    copyText(commands);
}

/**
 * MikroTik instruction labels for display
 */
const instructionLabels: Record<string, string> = {
    create_interface: "Create WireGuard Interface",
    assign_ip: "Assign IP Address",
    add_peer: "Add Server as Peer",
    route: "Add Route",
    firewall_allow_udp: "Allow WireGuard UDP",
    nat_masquerade: "NAT Masquerade",
    firewall_input_accept: "Allow WG Input",
    firewall_forward_in: "Allow WG Forward In",
    firewall_forward_out: "Allow WG Forward Out",
};

function submitForm() {
    showLoader(isEdit.value ? "Updating..." : "Saving...");
    if (isEdit.value) {
        axios.post(`/api/configuration/routers/${form.id}?_method=PUT`, form)
            .then((res: any) => {
                hideLoader();
                swalNotification("success", "Router updated successfully");
                const idx = routers.value.findIndex(r => r.id === form.id);
                if (idx !== -1) routers.value[idx] = { ...form };
                closeModal();
            })
            .catch((err: any) => {
                hideLoader();
                swalNotification("error", err.response?.data?.message || "Failed to save router.");
            });
    } else {
        axios.post("/api/configuration/routers", form)
            .then((res: any) => {
                hideLoader();
                swalNotification("success", "Router added successfully");
                routers.value.unshift(res.data.configuration);
                closeModal();
            })
            .catch((err: any) => {
                hideLoader();
                swalNotification("error", err.response?.data?.message || "Failed to save router.");
            });
    }
}

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
        axios.post(`/api/configuration/routers/${router.id}?_method=DELETE`, {})
            .then(() => {
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

function onScroll(e: Event) {
    const el = e.target as HTMLElement;
    if (el.scrollTop + el.clientHeight >= el.scrollHeight - 10) {
        fetchRouters();
    }
}

function testRouterConnection(id: number) {
    showLoader("Testing connection...");
    axios.post(`/api/configuration/routers/${id}/test`)
        .then((res: any) => {
            hideLoader();
            if (res.status === 200) {
                swalNotification("success", "Router connection successful.");
            } else {
                swalNotification("error", res.data?.error || "Failed to connect to router.");
            }
        })
        .catch(() => {
            hideLoader();
            swalNotification("error", "Failed to connect to router.");
        });
}

onMounted(() => {
    const token = usePage().props.auth.user.api_token;
    if (token) {
        axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
    }
    state.currentUser = usePage().props.auth.user;
    fetchRouters();
});
</script>

<template>
    <section class="container-fluid">
        <Head title="Router Configuration" />
        <div class="d-flex justify-content-between mt-2 py-1">
            <h4 class="fw-bold">Router Configurations</h4>
            <button class="btn btn-primary btn-sm" @click="openModal(false)"
                v-if="hasPermission('create_router', state.currentUser?.permissions_list)">
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
                                <a href="#" class="text-primary" @click.prevent="openModal(true, router)"
                                    v-if="hasPermission('edit_router', state.currentUser?.permissions_list)">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="#" class="text-danger" @click.prevent="deleteRouter(router)"
                                    v-if="hasPermission('delete_router', state.currentUser?.permissions_list)">
                                    <i class="fas fa-trash"></i>
                                </a>
                                <a href="#" class="text-success" @click.prevent="testRouterConnection(router.id)"
                                    v-if="hasPermission('test_connection', state.currentUser?.permissions_list)">
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

        <!-- Edit Modal (simple form, no wizard) -->
        <div v-if="showModal && isEdit" class="modal fade show d-block" tabindex="-1"
            style="background:rgba(0,0,0,0.3)">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Router</h5>
                        <button type="button" class="btn-close" @click="closeModal"></button>
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
                                <input v-model.number="form.port" type="number" class="form-control" required
                                    min="1" />
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Username</label>
                                <input v-model="form.username" type="text" class="form-control" required
                                    maxlength="100" autocomplete="off" />
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <input v-model="form.password" type="password" class="form-control"
                                    autocomplete="off" />
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" @click="closeModal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Update</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Add Router Wizard -->
        <div v-if="showModal && !isEdit" class="modal fade show d-block" tabindex="-1"
            style="background:rgba(0,0,0,0.3)">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Add Router</h5>
                        <button type="button" class="btn-close" @click="closeModal"></button>
                    </div>

                    <!-- Step indicator -->
                    <div class="px-4 pt-3">
                        <div class="d-flex align-items-center justify-content-center gap-2 mb-0">
                            <span class="wizard-step" :class="{ active: wizardStep === 1, done: wizardStep > 1 }">
                                <span class="step-num">1</span>
                                <span class="step-label">WireGuard</span>
                            </span>
                            <span class="wizard-line" :class="{ done: wizardStep > 1 }"></span>
                            <span class="wizard-step" :class="{ active: wizardStep === 2, done: wizardStep > 2 }">
                                <span class="step-num">2</span>
                                <span class="step-label">MikroTik</span>
                            </span>
                            <span class="wizard-line" :class="{ done: wizardStep > 2 }"></span>
                            <span class="wizard-step" :class="{ active: wizardStep === 3 }">
                                <span class="step-num">3</span>
                                <span class="step-label">Save</span>
                            </span>
                        </div>
                    </div>

                    <div class="modal-body">
                        <!-- STEP 1: WireGuard Setup -->
                        <div v-if="wizardStep === 1">
                            <!-- Default: Auto-create peer -->
                            <div v-if="!wgManualMode">
                                <p class="text-muted mb-3">Enter a name for the router to automatically set
                                    up a WireGuard VPN tunnel.</p>
                                <div class="mb-3">
                                    <label class="form-label">Router Name</label>
                                    <input v-model="wgPeerName" type="text" class="form-control"
                                        placeholder="e.g. hotel-lobby" maxlength="100" />
                                    <small class="text-muted">Use lowercase letters, numbers, and hyphens only</small>
                                </div>
                                <button class="btn btn-primary w-100" @click="autoCreatePeer"
                                    :disabled="wgLoading">
                                    <span v-if="wgLoading"
                                        class="spinner-border spinner-border-sm me-1"></span>
                                    <i v-else class="fas fa-plus me-1"></i>
                                    Create WireGuard Peer
                                </button>

                                <hr class="my-3" />
                                <div class="d-flex justify-content-between">
                                    <button class="btn btn-link text-muted btn-sm p-0"
                                        @click="wgManualMode = true">
                                        I'll add the peer manually
                                    </button>
                                    <button class="btn btn-link text-muted btn-sm p-0"
                                        @click="skipWireguard">
                                        Skip (no VPN needed)
                                    </button>
                                </div>
                            </div>

                            <!-- Manual mode: confirmation gate -->
                            <div v-if="wgManualMode && !wgManualConfirmed">
                                <div class="alert alert-warning mb-3">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <strong>Manual setup requires terminal access</strong>
                                    <p class="mb-0 mt-2 small">You'll need to SSH into the VPN server and
                                        run a shell command. Please confirm that you are familiar with
                                        Linux, Ubuntu 22, and shell commands before proceeding.</p>
                                </div>
                                <div class="d-flex gap-2">
                                    <button class="btn btn-primary"
                                        @click="wgManualConfirmed = true">
                                        Yes, I'm familiar - continue
                                    </button>
                                    <button class="btn btn-outline-secondary"
                                        @click="wgManualMode = false">
                                        Go back
                                    </button>
                                </div>
                            </div>

                            <!-- Manual mode: paste JSON -->
                            <div v-if="wgManualMode && wgManualConfirmed">
                                <div class="alert alert-info small">
                                    <strong>Run this command on your VPN server:</strong>
                                    <div class="d-flex align-items-start mt-2 gap-2">
                                        <code class="d-block flex-grow-1 bg-dark text-light p-2 rounded"
                                            style="font-size: 0.85rem; word-break: break-all;">sudo bash
                                            /var/www/superspotwifi/wireguard/add-wg-peer-json.sh
                                            ROUTER_NAME</code>
                                        <button class="btn btn-sm btn-outline-light flex-shrink-0"
                                            @click="copyText('sudo bash /var/www/superspotwifi/wireguard/add-wg-peer-json.sh ROUTER_NAME')">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </div>
                                    <small class="d-block mt-2 text-muted">Replace
                                        <strong>ROUTER_NAME</strong> with a name for this router
                                        (e.g. hotel-lobby)</small>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Paste the JSON output below:</label>
                                    <textarea v-model="wgPastedJson" class="form-control font-monospace"
                                        rows="6"
                                        placeholder='{"peer_name": "...", "peer_ip": "...", ...}'></textarea>
                                </div>
                                <div class="d-flex gap-2">
                                    <button class="btn btn-primary" @click="parseManualOutput"
                                        :disabled="wgLoading">
                                        <span v-if="wgLoading"
                                            class="spinner-border spinner-border-sm me-1"></span>
                                        Parse & Continue
                                    </button>
                                    <button class="btn btn-outline-secondary"
                                        @click="wgManualMode = false; wgManualConfirmed = false">
                                        Back
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- STEP 2: MikroTik Configuration -->
                        <div v-if="wizardStep === 2 && wireguardData">
                            <p class="text-muted mb-3">Run these commands on your MikroTik router (via
                                Winbox terminal or SSH).</p>

                            <div class="d-flex justify-content-end mb-2">
                                <button class="btn btn-outline-primary btn-sm" @click="copyAllCommands">
                                    <i class="fas fa-copy me-1"></i> Copy All Commands
                                </button>
                            </div>

                            <div class="mikrotik-commands">
                                <div v-for="(cmd, key) in wireguardData.mikrotik_instructions" :key="key"
                                    class="mb-2">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted fw-semibold">{{
                                            instructionLabels[key] || key
                                        }}</small>
                                        <button class="btn btn-sm btn-link p-0" @click="copyText(cmd)"
                                            title="Copy">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </div>
                                    <code class="d-block bg-dark text-light p-2 rounded mt-1"
                                        style="font-size: 0.8rem; word-break: break-all;">{{ cmd }}</code>
                                </div>
                            </div>
                        </div>

                        <!-- STEP 3: Router Details -->
                        <div v-if="wizardStep === 3">
                            <p class="text-muted mb-3" v-if="wireguardData">Router details have been
                                pre-filled from the WireGuard setup. Enter the MikroTik API credentials
                                and save.</p>
                            <p class="text-muted mb-3" v-else>Enter the router connection details.</p>

                            <form @submit.prevent="submitForm" id="routerForm">
                                <div class="mb-3">
                                    <label class="form-label">Name</label>
                                    <input v-model="form.name" type="text" class="form-control" required
                                        maxlength="100" />
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Host</label>
                                    <input v-model="form.host" type="text" class="form-control" required
                                        maxlength="60" />
                                    <small v-if="wireguardData" class="text-muted">WireGuard tunnel IP
                                        (pre-filled)</small>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">API Port</label>
                                    <input v-model.number="form.port" type="number" class="form-control"
                                        required min="1" />
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">API Username</label>
                                    <input v-model="form.username" type="text" class="form-control" required
                                        maxlength="100" autocomplete="off" />
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">API Password</label>
                                    <input v-model="form.password" type="password" class="form-control"
                                        autocomplete="off" />
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Wizard footer -->
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" @click="closeModal">Cancel</button>
                        <button v-if="wizardStep > 1" type="button" class="btn btn-outline-secondary"
                            @click="wizardStep--">
                            <i class="fas fa-arrow-left me-1"></i> Back
                        </button>
                        <button v-if="wizardStep === 2" type="button" class="btn btn-primary"
                            @click="wizardStep = 3">
                            I've configured the router <i class="fas fa-arrow-right ms-1"></i>
                        </button>
                        <button v-if="wizardStep === 3" type="submit" form="routerForm"
                            class="btn btn-success">
                            <i class="fas fa-save me-1"></i> Save Router
                        </button>
                    </div>
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

/* Wizard step indicator */
.wizard-step {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
}

.wizard-step .step-num {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: #dee2e6;
    color: #6c757d;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.85rem;
}

.wizard-step.active .step-num {
    background: #0d6efd;
    color: #fff;
}

.wizard-step.done .step-num {
    background: #198754;
    color: #fff;
}

.wizard-step .step-label {
    font-size: 0.75rem;
    color: #6c757d;
}

.wizard-step.active .step-label {
    color: #0d6efd;
    font-weight: 600;
}

.wizard-step.done .step-label {
    color: #198754;
}

.wizard-line {
    width: 60px;
    height: 2px;
    background: #dee2e6;
    margin-bottom: 20px;
}

.wizard-line.done {
    background: #198754;
}

.mikrotik-commands code {
    white-space: pre-wrap;
}

.font-monospace {
    font-family: 'Courier New', monospace;
    font-size: 0.85rem;
}

@media (max-width: 991px) {
    .bg-white {
        padding: 0.5rem !important;
    }

    .table {
        font-size: 0.95rem;
    }

    .wizard-line {
        width: 30px;
    }
}
</style>
