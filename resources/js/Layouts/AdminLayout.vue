<template>
    <!-- Sidebar -->
    <aside class="sidebar gradient-bg border-end">
        <div class="p-4 d-flex align-items-center">
            <img src="@/assets/images/superspotwifi-logo.png" alt="Logo" class="me-3 rounded-circle" width="60" />
            <h5 class="mb-0 fw-bold text-white">SuperSpot</h5>
        </div>

        <nav class="nav flex-column px-3 py-3">
            <a class="nav-link" href="#" @click="goTo('dashboard')" :class="{ active: isActive(['dashboard']) }">
                <i class="fas fa-tachometer-alt me-2"></i> Dashboard
            </a>
            <a class="nav-link" href="#" @click="goTo('vouchers.list')"
                :class="{ active: isActive(['vouchers.list']) }">
                <i class="fas fa-ticket me-2"></i> Vouchers
            </a>
            <a class="nav-link" href="#" @click="goTo('vouchers.index')"
                :class="{ active: isActive(['vouchers.index']) }">
                <i class="fas fa-mobile-retro me-2"></i> Data Plans
            </a>

            <!-- Payments -->
            <a class="nav-link" href="#" @click="goTo('payments.index')"
                :class="{ active: isActive(['payments.index']) }">
                <i class="fas fa-money-check-dollar me-2"></i> Payments
            </a>

            <!-- Router Collapse -->
            <div>
                <button class="btn btn-toggle w-100 text-start" data-bs-toggle="collapse"
                    data-bs-target="#router-collapse" aria-expanded="false"
                    @click="toggleArrowIcon('router-arrow-icon')">
                    <span><i class="fas fa-wifi me-2"></i> Router</span>
                    <i class="fas fa-chevron-down rotate-icon" id="router-arrow-icon"></i>
                </button>
                <div class="collapse" id="router-collapse">
                    <ul class="list-unstyled ps-4 mt-2">
                        <li><a href="#" class="nav-link" :class="{ active: isActive(['routers.index']) }"
                                @click="goTo('routers.index')">Configuration</a></li>
                        <li><a href="#" class="nav-link" :class="{ active: isActive(['routers.logs']) }"
                                @click="goTo('routers.logs')">Logs</a></li>
                    </ul>
                </div>
            </div>

            <!-- Users -->
            <a class="nav-link" href="#" @click="goTo('users.index')" :class="{ active: isActive(['users.index']) }">
                <i class="fas fa-users me-2"></i> Users
            </a>
        </nav>
    </aside>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Topbar -->
        <header class="topbar gradient-bg d-flex justify-content-between align-items-center px-4 py-3">
            <input type="text" class="form-control search-bar" placeholder="Search..." v-model="searchQuery"
                @input="emitSearch" />

            <div class="d-flex align-items-center">
                <i class="fas fa-bell text-white fs-5 me-3"></i>
                <div class="dropdown">
                    <button class="btn btn-link p-0 border-0 dropdown-toggle" type="button" id="userMenu"
                        data-bs-toggle="dropdown" aria-expanded="false">
                        <img :src="`https://ui-avatars.com/api/?name=${currentUser?.name}`"
                            class="rounded-circle border border-white" width="36" height="36" alt="User Avatar" />
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userMenu">
                        <li>
                            <button class="dropdown-item text-danger" @click="logout">
                                <i class="fas fa-sign-out-alt me-2"></i> Logout
                            </button>
                        </li>
                    </ul>
                </div>
            </div>
        </header>

        <!-- Page Content -->
        <main class="mt-4 p-4 bg-light container-fluid">
            <slot />
        </main>
    </div>
</template>

<script>
import emitter from '@/eventBus';
import { debounce } from 'lodash';

export default {
    name: 'AdminLayout',
    data() {
        return {
            currentUser: this.$page.props.auth?.user || null,
            currentRoute: this.$page.props.route || null,
            searchQuery: '',
        };
    },
    mixins: [emitter],
    mounted() {
        this.initCollapses();
    },
    methods: {
        goTo(routeName) {
            this.$inertia.visit(route(routeName));
        },
        logout() {
            this.$inertia.post(route('logout'));
        },
        toggleArrowIcon(id) {
            const icon = document.getElementById(id);
            if (icon) {
                icon.classList.toggle('fa-chevron-down');
                icon.classList.toggle('fa-chevron-up');
                icon.classList.toggle('rotate');
            }
        },
        isActive(routeNames = []) {
            const currentUrl = window.location.href;
            return routeNames.some(name => currentUrl.includes(route(name)));
        },
        emitSearch: debounce(function () {
            emitter.emit('search', this.searchQuery);
        }, 300),
        initCollapses() {
            document.querySelectorAll('.collapse').forEach((el) => {
                el.addEventListener('show.bs.collapse', () => {
                    const icon = el.previousElementSibling?.querySelector('i.rotate-icon');
                    if (icon) icon.classList.add('rotate');
                });
                el.addEventListener('hide.bs.collapse', () => {
                    const icon = el.previousElementSibling?.querySelector('i.rotate-icon');
                    if (icon) icon.classList.remove('rotate');
                });
            });
        }
    },
};
</script>

<style scoped>
.gradient-bg {
    background: linear-gradient(135deg, #5327ef 0%, #ff5f6d 100%) !important;
}

/* Sidebar */
.sidebar {
    width: 260px;
    height: 100vh;
    position: fixed;
    top: 0;
    left: 0;
    overflow-y: auto;
    z-index: 1030;
    box-shadow: 2px 0 16px rgba(0, 0, 0, 0.2);
    color: white;
}

.nav-link,
.btn-toggle {
    color: white !important;
    font-weight: 500;
    padding: 0.6rem 1rem;
    border-radius: 0.375rem;
    transition: all 0.2s ease-in-out;
}

.nav-link:hover,
.btn-toggle:hover {
    background-color: rgba(255, 255, 255, 0.1);
}

.nav-link.active {
    background-color: rgba(255, 255, 255, 0.2);
}

/* Topbar */
.topbar {
    position: fixed;
    top: 0;
    left: 260px;
    right: 0;
    height: 64px;
    z-index: 1031;
    color: white;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

/* Search bar */
.search-bar {
    background: rgba(255, 255, 255, 0.1);
    border: none;
    color: white;
    border-radius: 30px;
    padding: 0.5rem 1rem;
    width: 250px;
}

.search-bar::placeholder {
    color: rgba(255, 255, 255, 0.6);
}

.search-bar:focus {
    outline: none;
    box-shadow: 0 0 0 0.2rem rgba(255, 255, 255, 0.3);
    background: rgba(255, 255, 255, 0.2);
}

/* Main content */
.main-content {
    margin-left: 260px;
    padding-top: 64px;
}

/* Chevron icon animation */
.rotate-icon {
    transition: transform 0.3s ease;
}

.rotate {
    transform: rotate(180deg);
}

/* Responsive */
@media (max-width: 991px) {
    .sidebar {
        width: 64px;
    }

    .topbar {
        left: 64px;
    }

    .main-content {
        margin-left: 64px;
    }

    .sidebar h5,
    .sidebar .p-4 img {
        display: none;
    }
}

@media (max-width: 600px) {
    .sidebar {
        display: none;
    }

    .topbar,
    .main-content {
        left: 0;
        margin-left: 0;
    }
}
</style>
