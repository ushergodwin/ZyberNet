<script setup lang="ts">
import { ref, reactive, onMounted, onUnmounted, nextTick, watch } from "vue";
import { Head, usePage } from "@inertiajs/vue3";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import {
    showLoader,
    hideLoader,
    swalNotification,
    swalConfirm,
    number_format,
    hasPermission,
} from "@/mixins/helpers.mixin.js";
import axios from "axios";
import emitter from "@/eventBus";
import flatpickr from "flatpickr";

defineOptions({ layout: AdminLayout });

// Date picker refs
const dateFromRef = ref<HTMLInputElement | null>(null);
const dateToRef = ref<HTMLInputElement | null>(null);

const state = reactive({
    vouchers: [],
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
    searchQuery: "",
    dateFrom: "",
    dateTo: "",
    voucherTransaction: null,
    showTransactionModal: false,
    selectedVoucher: null,
    form: { package_id: null, quantity: 1, router_id: null, voucher_length: 4, voucher_format: 'nl' },
    showCreateModal: false,
    packages: [],
    generatedVouchers: [],
    showPrintModal: false,
    transactionForm: {
        phone_number: "",
        currency: "UGX",
        amount: 0,
        status: "successful",
    },
    selectedRouterId: null,
    routers: [],
    selectedRouter: null,
    selectedVouchers: [],
    currentUser: null,
    loadingRouters: false,
});

onMounted(() => {
    const token = usePage().props.auth.user.api_token;
    if (token) axios.defaults.headers.common["Authorization"] = `Bearer ${token}`;
    state.currentUser = usePage().props.auth.user;

    loadRouters();

    emitter.on("search", handleSearch);

    // Restore cached vouchers
    const cached = localStorage.getItem("recentlyGeneratedVouchers");
    if (cached && cached !== "undefined") {
        state.generatedVouchers = JSON.parse(cached);
    }

    // Flatpickr dateFrom
    flatpickr(dateFromRef.value as HTMLInputElement, {
        dateFormat: "Y-m-d",
        onChange: ([date]) => {
            state.dateFrom = date ? date.toISOString().split("T")[0] : "";
            handleDateFilter();
        },
    });

    // Flatpickr dateTo
    flatpickr(dateToRef.value as HTMLInputElement, {
        dateFormat: "Y-m-d",
        onChange: ([date]) => {
            state.dateTo = date ? date.toISOString().split("T")[0] : "";
            handleDateFilter();
        },
    });
});

onUnmounted(() => emitter.off("search", handleSearch));

/** Load vouchers with filters */
const loadVouchers = (page = 1) => {
    if (state.loading) return;
    state.loading = true;

    let url = `/api/vouchers?page=${page}`;
    if (state.searchQuery) url += `&search=${encodeURIComponent(state.searchQuery)}`;
    if (state.selectedRouterId) url += `&router_id=${state.selectedRouterId}`;
    if (state.dateFrom) url += `&date_from=${state.dateFrom}`;
    if (state.dateTo) url += `&date_to=${state.dateTo}`;

    axios
        .get(url)
        .then((response) => {
            const data = response.data;
            state.vouchers = data.data;
            state.pagination = {
                current_page: data.current_page,
                last_page: data.last_page,
                per_page: data.per_page,
                total: data.total,
                from: data.from,
                to: data.to,
            };
            state.error = data.data.length === 0 ? "No vouchers found." : null;
            state.loading = false;
        })
        .catch((error) => {
            state.error =
                error.response?.data?.message || "Failed to load vouchers.";
            console.error(error);
            state.loading = false;
        });
};

/** Load packages */
const loadPackages = () => {
    let url = "/api/configuration/vouchers/packages";
    if (state.selectedRouterId) url += `?router_id=${state.selectedRouterId}`;
    axios
        .get(url)
        .then((response) => (state.packages = response.data.packages))
        .catch((err) =>
            swalNotification(
                "error",
                err.response?.data?.message || "Failed to load packages"
            )
        );
};

/** Load routers */
const loadRouters = () => {
    state.loadingRouters = true;
    axios
        .get("/api/configuration/routers?no_paging=true")
        .then((response) => {
            state.routers = response.data;
            if (!state.selectedRouterId && state.routers.length > 0) {
                state.selectedRouterId = state.routers[0].id;
                state.form.router_id = state.selectedRouterId;
            }
            state.loadingRouters = false;
            loadPackages();
            loadVouchers(1);
        })
        .catch((err) =>
            swalNotification(
                "error",
                err.response?.data?.message || "Failed to load routers"
            )
        );
};

/** Watch router changes */
watch(
    () => state.selectedRouterId,
    (newRouterId) => {
        state.form.router_id = newRouterId;
        state.selectedRouter =
            state.routers.find((r) => r.id === newRouterId) || null;
        loadVouchers(1);
        loadPackages();
    }
);

/** Handle search */
const handleSearch = (query: string) => {
    if (query === "clear_filter") {
        state.searchQuery = "";
        state.dateFrom = "";
        state.dateTo = "";
        if (dateFromRef.value) dateFromRef.value._flatpickr?.clear();
        if (dateToRef.value) dateToRef.value._flatpickr?.clear();
        loadVouchers(1);
        return;
    }
    state.searchQuery = query;
    loadVouchers(1);
};

/** Date filter */
const handleDateFilter = () => loadVouchers(1);

/** Pagination */
const handlePageChange = (page: number) => {
    if (page < 1 || page > state.pagination.last_page) return;
    loadVouchers(page);
};

/** Generate vouchers */
const generateVouchers = () => {
    if (!state.form.package_id || !state.form.router_id) {
        swalNotification("error", "Please select a router and package.");
        return;
    }
    swalConfirm
        .fire({
            title: "Generate Vouchers",
            text: `Generate ${state.form.quantity} vouchers?`,
            icon: "warning",
            showCancelButton: true,
            confirmButtonText: "Yes, generate!",
            reverseButtons: true,
        })
        .then((result) => {
            if (result.isConfirmed) {
                showLoader("generating...");
                axios
                    .post("/api/vouchers/generate", {
                        ...state.form
                    })
                    .then((response) => {
                        hideLoader();
                        state.generatedVouchers = response.data.vouchers;
                        localStorage.setItem(
                            "recentlyGeneratedVouchers",
                            JSON.stringify(state.generatedVouchers)
                        );
                        state.showCreateModal = false;
                        state.form.package_id = null;
                        state.form.quantity = 1;
                        swalNotification(
                            "success",
                            `${state.generatedVouchers.length} vouchers generated.`
                        ).then(() => {
                            state.showPrintModal = true;
                            nextTick(() =>
                                printVouchers(state.generatedVouchers)
                            );
                            loadVouchers(1);
                        });
                    })
                    .catch((err) => {
                        hideLoader();
                        swalNotification(
                            "error",
                            err.response?.data?.message ||
                            "Failed to generate vouchers."
                        );
                    });
            }
        });
};

/** Print helpers */
const printVouchers = (vouchers) => {
    const routerName = state.selectedRouter
        ? state.selectedRouter.name
        : "All Routers";
    const rows = [];
    const columnsPerRow = 4;

    for (let i = 0; i < vouchers.length; i += columnsPerRow) {
        const row = vouchers
            .slice(i, i + columnsPerRow)
            .map(
                (v) => `
            <td class="voucher-cell">
                <table class="inner-table">
                    <tr><td colspan="2" class="router-name"><strong>${routerName}</strong></td></tr>
                    <tr><td><strong>Voucher</strong></td><td>${v.code}</td></tr>
                    <tr><td><strong>Price</strong></td><td>UGX${parseInt(
                    v.package?.price || 0
                ).toLocaleString()}</td></tr>
                    <tr><td><strong>Expires In</strong></td><td>${v.expires_in || "—"
                    }</td></tr>
                </table>
            </td>
        `
            )
            .join("");
        rows.push(`<tr>${row}</tr>`);
    }

    const printWindow = window.open("", "_blank");
    printWindow.document.write(`
        <html>
            <head>
                <title>${routerName} Vouchers</title>
                <style>
                    body { font-family: Arial, sans-serif; padding: 10px; font-size: 11px; }
                    table { width: 100%; border-collapse: separate; border-spacing: 8px 6px; }
                    .voucher-cell { border: 1px solid #333; padding: 4px; vertical-align: top; }
                    .inner-table { width: 100%; border-collapse: collapse; }
                    .inner-table td { padding: 2px 4px; font-size: 11px; }
                    .router-name { text-align: center; font-size: 12px; background-color: #f1f1f1; font-weight: bold; padding: 2px 0; border-bottom: 1px solid #ccc; }
                    .inner-table tr:not(:first-child) td:first-child { width: 55px; font-weight: bold; }
                </style>
            </head>
            <body>
                <table>${rows.join("")}</table>
            </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.print();
};

const printSingleVoucher = (voucher) => printVouchers([voucher]);
const reprintLastBatch = () => {
    const cached = localStorage.getItem("recentlyGeneratedVouchers");
    if (cached) printVouchers(JSON.parse(cached));
    else swalNotification("info", "No recent vouchers found in memory.");
};

/** Transaction logic */
const getVoucherTransaction = (voucher) => {
    state.voucherTransaction = null;
    state.selectedVoucher = voucher;
    state.transactionForm.amount = voucher.package.price;
    state.showTransactionModal = false;
    showLoader();
    axios
        .get(`/api/vouchers/${voucher.id}/transaction`)
        .then((response) => {
            hideLoader();
            state.voucherTransaction = response.data.transaction;
            state.showTransactionModal = true;
        })
        .catch((err) => {
            hideLoader();
            swalNotification(
                "error",
                err.response?.data?.message || "Failed to load transaction."
            );
        });
};

const saveTransaction = () => {
    swalConfirm
        .fire({
            title: "Save Transaction",
            text: `Save transaction for voucher ${state.selectedVoucher.code}?`,
            icon: "warning",
            showCancelButton: true,
            confirmButtonText: "Yes, save!",
            reverseButtons: true,
        })
        .then((result) => {
            if (result.isConfirmed) {
                showLoader("Saving transaction...");
                axios
                    .post(
                        `/api/vouchers/${state.selectedVoucher.id}/transaction`,
                        state.transactionForm
                    )
                    .then((response) => {
                        hideLoader();
                        if (response.status === 200) {
                            swalNotification(
                                "success",
                                "Transaction saved successfully."
                            );
                            state.showTransactionModal = false;
                            loadVouchers(state.pagination.current_page);
                        } else
                            swalNotification(
                                "error",
                                "Failed to save transaction."
                            );
                    })
                    .catch((err) => {
                        hideLoader();
                        swalNotification(
                            "error",
                            err.response?.data?.message ||
                            "Failed to save transaction."
                        );
                    });
            }
        });
};

/** Delete voucher */
const deleteVoucher = (voucher) => {
    state.selectedVoucher = voucher;
    swalConfirm
        .fire({
            title: "Delete Voucher",
            text: `Are you sure you want to delete this voucher? It will also be deleted from the router.`,
            icon: "warning",
            showCancelButton: true,
            confirmButtonText: "Yes, delete!",
            reverseButtons: true,
        })
        .then((result) => {
            if (result.isConfirmed) {
                showLoader("Deleting voucher...");
                axios
                    .delete(`/api/vouchers/${voucher.code}`)
                    .then((response) => {
                        hideLoader();
                        swalNotification(
                            "success",
                            response.data.message || "Voucher deleted."
                        );
                        loadVouchers(state.pagination.current_page);
                    })
                    .catch((err) => {
                        hideLoader();
                        swalNotification(
                            "error",
                            err.response?.data?.message ||
                            "Failed to delete voucher."
                        );
                    });
            }
        });
};

/** Select vouchers */
const selectVoucher = (voucher) => {
    if (state.selectedVouchers.includes(voucher))
        state.selectedVouchers = state.selectedVouchers.filter((v) => v !== voucher);
    else state.selectedVouchers.push(voucher);
};

const selectAllVouchers = () => {
    if (state.selectedVouchers.length === state.vouchers.length)
        state.selectedVouchers = [];
    else state.selectedVouchers = state.vouchers.slice(0, state.pagination.per_page);
};

const printSelectedVouchers = () => {
    if (!state.selectedVouchers.length)
        return swalNotification("info", "No vouchers selected.");
    state.selectedRouter =
        state.routers.find((r) => r.id === state.selectedRouterId) || null;
    printVouchers(state.selectedVouchers);
    state.selectedVouchers = [];
};

const deleteSelectedVouchers = () => {
    if (!state.selectedVouchers.length)
        return swalNotification("info", "No vouchers selected.");
    state.selectedRouter =
        state.routers.find((r) => r.id === state.selectedRouterId) || null;
    swalConfirm.fire({
        icon: 'warning',
        title: 'Confirm Deleting Vouchers',
        text: 'You are about to delete all the selected vouchers. This action cannot be undone! Proceed?',
        confirmButtonText: 'Yes, Proceed',
        reverseButtons: true,
        showCancelButton: true,
    }).then(results => {
        if (results.isConfirmed) {
            const voucherIds = state.selectedVouchers.map((v) => v.id);
            showLoader();
            axios.post('/api/configuration/vouchers/delete-batch', {
                vouchers: voucherIds
            }).then(response => {
                hideLoader();
                if (response.status === 200) {
                    swalNotification('success', response.data.message)
                        .then(() => {
                            state.selectedVouchers = [];
                            loadVouchers(state.pagination.current_page);
                        })
                } else {
                    swalNotification('error', response.data.message);
                }
            }).catch(error => {
                swalNotification('error', error.response.data?.message || 'An error occurred while deleting the selected vouchers. Please try again.')
            });
        }
    })
}
</script>

<template>
    <section class="container-fluid">

        <Head title="Vouchers" />
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h4 class="h3">Vouchers</h4>
            <section>
                <div class="d-flex gap-3 flex-wrap">
                    <!-- Router Selection -->
                    <select v-model="state.selectedRouterId" class="form-select w-auto">
                        <option :value="0">All Routers</option>
                        <option v-for="router in state.routers" :key="router.id" :value="router.id">{{ router.name }}
                        </option>
                    </select>
                </div>
            </section>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-2">
            <section>
                <div class="d-flex gap-3 flex-wrap">
                    <!-- Search / Status Filter -->
                    <select class="form-select w-auto" v-model="state.searchQuery"
                        @change="handleSearch(state.searchQuery)">
                        <option value="">All Vouchers</option>
                        <option value="activated:Y">Activated</option>
                        <option value="activated:N">Not Activated</option>
                        <option value="expired">Expired</option>
                        <option value="active">Not Expired</option>
                    </select>

                    <!-- From Date -->
                    <input type="text" ref="dateFromRef" class="form-control w-auto" placeholder="From Date" readonly />
                    <!-- To Date -->
                    <input type="text" ref="dateToRef" class="form-control w-auto" placeholder="To Date" readonly />

                    <!-- Reset Filter -->
                    <button class="btn btn-secondary" @click="handleSearch('clear_filter')">
                        Clear Filters
                    </button>
                    <button class="btn btn-primary" @click="state.showCreateModal = true"
                        v-if="hasPermission('create_vouchers', state.currentUser?.permissions_list)">
                        <i class="fas fa-plus"></i> &nbsp;&nbsp;<i class="fas fa-ticket"></i>
                    </button>
                    <button class="btn btn-secondary" v-if="state.selectedVouchers.length > 1"
                        @click="printSelectedVouchers">
                        <i class="fas fa-print"></i> Reprint Vouchers
                    </button>
                    <button class="btn btn-danger" v-if="state.selectedVouchers.length > 1"
                        @click="deleteSelectedVouchers">
                        <i class="fas fa-trash-alt"></i> Delete Vouchers
                    </button>
                </div>
            </section>
        </div>

        <!-- Vouchers Table -->
        <div class="card card-body shadow">
            <table class="table table-striped table-hover" v-if="state.vouchers.length">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Package</th>
                        <th>Amount (UGX)</th>
                        <th>Activated</th>
                        <th>Status</th>
                        <th class="d-flex gap-2">Actions
                            <div class="form-check"
                                v-if="hasPermission('print_vouchers', state.currentUser?.permissions_list)">
                                <input type="checkbox" class="form-check-input"
                                    :checked="state.selectedVouchers.length === state.vouchers.length"
                                    @change="selectAllVouchers">
                            </div>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="voucher in state.vouchers" :key="voucher.id">
                        <td>{{ voucher.code }}</td>
                        <td>{{ voucher.package.name }}</td>
                        <td>{{ number_format(voucher.package.price) }}</td>
                        <td>
                            <div v-if="voucher.activated_at">
                                <span class="badge bg-success">Y</span> {{ voucher.activated_at_time }}
                            </div>
                            <span class="badge bg-secondary" v-else>N</span>
                        </td>
                        <td>
                            <span class="badge bg-success" v-if="voucher.is_active && voucher.activated_at">
                                {{ voucher.expires_in ? `Expires In ${voucher.expires_in}` : '-' }}
                            </span>
                            <span class="badge bg-secondary" v-if="!voucher.activated_at">Inactive</span>
                            <span class="badge bg-danger"
                                v-if="!voucher.is_active && voucher.activated_at">Expired</span>
                        </td>
                        <td>
                            <div class="d-flex gap-3">
                                <a href="#" @click.prevent="getVoucherTransaction(voucher)" class="text-primary"><i
                                        class="fas fa-credit-card"></i></a>
                                <a href="#" v-if="hasPermission('print_vouchers', state.currentUser?.permissions_list)"
                                    @click.prevent="printSingleVoucher(voucher)" class="text-info"><i
                                        class="fas fa-print"></i></a>
                                <a href="#" v-if="hasPermission('delete_vouchers', state.currentUser?.permissions_list)"
                                    @click.prevent="deleteVoucher(voucher)" class="text-danger"><i
                                        class="fas fa-trash-alt"></i></a>
                                <div class="form-check"
                                    v-if="hasPermission('print_vouchers', state.currentUser?.permissions_list)">
                                    <input type="checkbox" class="form-check-input"
                                        :checked="state.selectedVouchers.includes(voucher)"
                                        @change="selectVoucher(voucher)">
                                </div>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div v-if="state.loading" class="text-center my-3"><i class="fas fa-spinner fa-spin"></i> Loading
                vouchers...</div>
            <div v-if="state.loadingRouters" class="text-center my-3"><i class="fas fa-spinner fa-spin"></i>
                Loading routers...</div>
            <div v-if="!state.vouchers.length && !state.loading && !state.loadingRouters"
                class="text-danger text-center my-3">
                <i class="fas fa-exclamation-triangle"></i> {{ state.error || "No vouchers found." }}
            </div>

            <!-- Pagination -->
            <div class="d-flex justify-content-end mt-3 gap-2" v-if="state.vouchers.length">
                <nav>
                    <ul class="pagination">
                        <li class="page-item" :class="{ disabled: state.pagination.current_page === 1 }">
                            <a href="#" class="page-link"
                                @click.prevent="handlePageChange(state.pagination.current_page - 1)"><i
                                    class="fas fa-chevron-left"></i></a>
                        </li>
                        <li v-for="page in state.pagination.last_page" :key="page" class="page-item"
                            :class="{ active: page === state.pagination.current_page }">
                            <a href="#" class="page-link" @click.prevent="handlePageChange(page)">{{ page }}</a>
                        </li>
                        <li class="page-item"
                            :class="{ disabled: state.pagination.current_page === state.pagination.last_page }">
                            <a href="#" class="page-link"
                                @click.prevent="handlePageChange(state.pagination.current_page + 1)"><i
                                    class="fas fa-chevron-right"></i></a>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>

        <!-- Create Voucher Modal -->
        <div v-if="state.showCreateModal" class="modal fade show d-block" tabindex="-1"
            style="background:rgba(0,0,0,0.3)">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Create Vouchers</h5>
                        <button type="button" class="btn-close" @click="state.showCreateModal = false"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Router -->
                        <div class="mb-3">
                            <label for="router_id" class="form-label">Select Router</label>
                            <select v-model="state.selectedRouterId" class="form-select input-rounded">
                                <option v-for="router in state.routers" :key="router.id" :value="router.id">
                                    {{ router.name }}
                                </option>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label for="package_id" class="form-label">Select Data Plan</label>
                                <select v-model="state.form.package_id" class="form-select input-rounded">
                                    <option value="" disabled> -- select a plan --</option>
                                    <option v-for="pkg in state.packages" :key="pkg.id" :value="pkg.id">{{ pkg.name
                                        }} - {{ pkg.formatted_price }}</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="quantity" class="form-label">Number of Vouchers</label>
                                <input type="number" v-model="state.form.quantity" min="1"
                                    class="form-control input-rounded" />
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="voucher_length" class="form-label">Voucher Length</label>
                                <select class="form-select" v-model="state.form.voucher_length">
                                    <option :value="4">4</option>
                                    <option :value="5">5</option>
                                    <option :value="6">6</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="voucher_format" class="form-label">Voucher Format</label>
                                <select class="form-select" v-model="state.form.voucher_format">
                                    <option value="n">Numbers Only</option>
                                    <option value="l">Letters Only</option>
                                    <option value="nl">Numbers & Letters</option>
                                </select>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end">
                            <button class="btn btn-primary" @click="generateVouchers" :disabled="state.loading">
                                <i class="fas fa-plus"></i> Generate Vouchers
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
