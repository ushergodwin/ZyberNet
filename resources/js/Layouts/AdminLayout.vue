<template>
    <!-- Sidebar with vivid gradient background -->
    <aside class="sidebar gradient-bg border-end shadow-sm">
        <div class="p-4 d-flex align-items-center">
            <img src="@/assets/images/zybernet-short-logo.png" alt="Logo" class="me-2 rounded-circle" width="70"
                height="70">
            <h5 class="mb-0 fw-bold">Zyber</h5>
        </div>

        <nav class="nav flex-column px-3 py-3">
            <a class="nav-link d-flex justify-content-between align-items-center mb-2" href="#"
                @click="goTo('dashboard')" :class="{ active: isActive(['dashboard']) }">
                <span><i class="fas fa-tachometer-alt me-2"></i> Dashboard</span>
            </a>
            <div>
                <button class="btn btn-toggle w-100 text-start d-flex justify-content-between align-items-center mb-2"
                    data-bs-toggle="collapse" data-bs-target="#resources-collapse" aria-expanded="false"
                    @click="toggleArrowIcon('voucher-arrow-icon')">
                    <span><i class="fas fa-ticket me-2"></i> Vouchers</span>
                    <i class="fas fa-chevron-down" id="voucher-arrow-icon"></i>
                </button>
                <div class="collapse" id="resources-collapse">
                    <ul class="list-unstyled ps-4">
                        <li><a href="#" class="nav-link" :class="{ active: isActive(['vouchers.list']) }"
                                @click="goTo('vouchers.list')">List</a></li>
                        <li><a href="#" class="nav-link" :class="{ active: isActive(['vouchers.index']) }"
                                @click="goTo('vouchers.index')">Packages</a></li>
                    </ul>
                </div>
            </div>
            <a class="nav-link d-flex justify-content-between align-items-center mb-2" href="#"
                @click="goTo('payments.index')" :class="{ active: isActive(['payments.index']) }">
                <span><i class="fas fa-money-check-dollar me-2"></i> Payments</span>
            </a>
            <div>
                <button class="btn btn-toggle w-100 text-start d-flex justify-content-between align-items-center mb-2"
                    data-bs-toggle="collapse" data-bs-target="#routers-collapse" aria-expanded="false"
                    @click="toggleArrowIcon('router-arrow-icon')">
                    <span><i class="fas fa-wifi me-2"></i> Router</span>
                    <i class="fas fa-chevron-down" id="router-arrow-icon"></i>
                </button>
                <div class="collapse" id="routers-collapse">
                    <ul class="list-unstyled ps-4">
                        <li><a href="#" class="nav-link" :class="{ active: isActive(['routers.index']) }"
                                @click="goTo('routers.index')">Configuration</a></li>
                        <li><a href="#" class="nav-link" :class="{ active: isActive(['routers.logs']) }"
                                @click="goTo('routers.logs')">Logs</a></li>
                    </ul>
                </div>
            </div>
            <a class="nav-link d-flex justify-content-between align-items-center mb-2" href="#"
                @click="goTo('users.index')" :class="{ active: isActive(['users.index']) }">
                <span><i class="fas fa-users me-2"></i> Users</span>
            </a>
            <!-- <a class="nav-link d-flex justify-content-between align-items-center mb-2" href="#">
                <span><i class="fas fa-cog me-2"></i> Settings</span>
            </a> -->
        </nav>
    </aside>

    <!-- Main layout -->
    <div class="main-content">
        <!-- Top Bar with vivid gradient background -->
        <header
            class="topbar gradient-bg d-flex justify-content-between align-items-center px-4 py-3 border-bottom shadow-sm">
            <input type="text" class="form-control rounded-pill w-25" placeholder="Search..." aria-label="Search"
                v-model="searchQuery" @input="emitSearch" autocomplete="off">
            <div class="d-flex align-items-center position-relative">
                <i class="fas fa-bell me-3 text-white" aria-label="Notifications"></i>
                <!-- User avatar dropdown for logout -->
                <div class="dropdown">
                    <button class="btn btn-link p-0 border-0 dropdown-toggle" type="button" id="userMenu"
                        data-bs-toggle="dropdown" aria-expanded="false" style="box-shadow:none;">
                        <img :src="`https://ui-avatars.com/api/?name=${currentUser?.name}`"
                            class="rounded-circle border border-white me-2" width="36" height="36" alt="Admin" />
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

        <!-- Scrollable Content -->
        <main class="bg-light mt-2 container-fluid">
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
            // Define any data properties if needed
            currentUser: null, // Placeholder for current user data
            currentRoute: null,
            searchQuery: '',
        };
    },
    mixins: [emitter],
    mounted() {
        const toggles = document.querySelectorAll('.btn-toggle');
        toggles.forEach(toggle => {
            const targetId = toggle.getAttribute('data-bs-target');
            const target = document.querySelector(targetId);
            const icon = toggle.querySelector('.rotate-icon');

            target.addEventListener('show.bs.collapse', () => {
                icon.classList.add('rotate');
            });
            target.addEventListener('hide.bs.collapse', () => {
                icon.classList.remove('rotate');
            });
        });

        // get current user from inertia
        this.currentUser = this.$page.props.auth.user || null;
        // get current route from inertia
        this.currentRoute = this.$page.props.route || null;
        console.log('this.$page.props', this.$page.props);
    },

    methods: {
        // Define any methods if needed

        goTo(routeName) {
            this.$inertia.visit(route(routeName));
        },
        // Logout method for user menu
        logout() {
            this.$inertia.post(route('logout'));
        },
        toggleArrowIcon(iconId) {
            const icon = document.getElementById(iconId);
            if (icon) {
                icon.classList.toggle('fa-chevron-down');
                icon.classList.toggle('fa-chevron-up');
            }
        },

        isActive(routeName = []) {
            if (!routeName || routeName.length === 0) {
                return false;
            }
            const currentUrl = window.location.href;
            return routeName.length > 0
                ? routeName.some(name => currentUrl.includes(route(name)))
                : route(routeName[0]) === window.location.href;
        },

        emitSearch: debounce(function () {
            emitter.emit('search', this.searchQuery);
        }, 300),
    },
};
</script>

<style scoped>
/* Vivid gradient background utility */
.gradient-bg {
    background: linear-gradient(135deg, #5327ef 0%, #ff5f6d 100%) !important;
}

/* Sidebar styles */
.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    width: 260px;
    height: 100vh;
    z-index: 1030;
    overflow-y: auto;
    box-shadow: 2px 0 16px 0 rgba(80, 80, 120, 0.08);
    border-right: 1px solid #ececec;
    color: #fff;
}

.sidebar h5 {
    color: #fff;
}

.nav-link,
.btn-toggle {
    border-radius: 0.375rem;
    transition: background 0.2s, color 0.2s;
    font-weight: 500;
    color: #fff !important;
}

.nav-link:hover,
.btn-toggle:hover,
.btn-toggle.active {
    background: rgba(255, 255, 255, 0.12);
    color: #fff !important;
}

/* Topbar styles */
.topbar {
    position: fixed;
    top: 0;
    left: 260px;
    right: 0;
    height: 64px;
    z-index: 1031;
    color: #fff;
    box-shadow: 0 2px 8px 0 rgba(80, 80, 120, 0.08);
}

.main-content {
    margin-left: 260px;
    padding-top: 64px;
    min-height: 100vh;
}


/* Responsive styles */
@media (max-width: 991px) {
    .sidebar {
        width: 64px;
        min-width: 64px;
        padding: 0;
    }

    .sidebar h5,
    .sidebar .p-4 img {
        display: none;
    }

    .main-content {
        margin-left: 64px;
    }

    .topbar {
        left: 64px;
    }
}

@media (max-width: 600px) {
    .sidebar {
        display: none;
    }

    .main-content {
        margin-left: 0;
    }

    .topbar {
        left: 0;
    }
}

/* Dropdown arrow rotation */
.rotate-icon {
    transition: transform 0.3s ease;
}

.rotate-icon.rotate {
    transform: rotate(90deg);
}

/* Sidebar button styling */
.btn-toggle {
    font-weight: 500;
    background: transparent;
    color: #333;
    border: none;
    padding: 0.5rem 0.75rem;
}

.nav-link.active {
    background: rgba(255, 255, 255, 0.12);
    color: #fff !important;
}
</style>
