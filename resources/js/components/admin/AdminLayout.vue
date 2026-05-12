<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useAdminAuth } from '../../composables/useAdminAuth';
import { toast } from 'vue3-toastify';
import { 
    LayoutDashboard, 
    ClipboardList, 
    Users, 
    CalendarDays, 
    CircleDollarSign, 
    LogOut,
    UserPlus,
    UserCircle,
    CheckCircle2,
    Clock,
    Coffee,
    ChevronLeft,
    ChevronRight,
    PanelLeftClose,
    PanelLeftOpen,
    Menu,
    X
} from 'lucide-vue-next';

const isCollapsed = ref(localStorage.getItem('admin_sidebar_collapsed') === 'true');
const isMobileMenuOpen = ref(false);
const isMobile = ref(window.innerWidth <= 768);

const updateIsMobile = () => {
    isMobile.value = window.innerWidth <= 768;
    if (!isMobile.value) {
        isMobileMenuOpen.value = false;
    }
};

onMounted(() => {
    window.addEventListener('resize', updateIsMobile);
});

onUnmounted(() => {
    window.removeEventListener('resize', updateIsMobile);
});

const toggleSidebar = () => {
    if (isMobile.value) {
        isMobileMenuOpen.value = !isMobileMenuOpen.value;
    } else {
        isCollapsed.value = !isCollapsed.value;
        localStorage.setItem('admin_sidebar_collapsed', isCollapsed.value);
    }
};

const closeMobileMenu = () => {
    isMobileMenuOpen.value = false;
};

const router = useRouter();
const route = useRoute();
const { adminUser, logout, isAdmin, isShiftLeader } = useAdminAuth();

const navItems = computed(() => {
    const items = [
        { label: 'Tổng quan', icon: LayoutDashboard, to: isAdmin() || isShiftLeader() ? '/admin/dashboard' : '/staff/dashboard', roles: ['admin', 'staff', 'shift_leader'] },
        { label: 'Quản lý đơn hàng', icon: ClipboardList, to: isAdmin() ? '/admin/orders' : '/staff/orders', roles: ['admin', 'staff', 'shift_leader'] },
        { label: 'Quản lý thành viên', icon: UserCircle, to: '/admin/members/list', roles: ['admin'] },
        { label: 'Quản lý nhân viên', icon: Users, to: '/admin/staff', roles: ['admin', 'shift_leader'] },
        { label: 'Quản lý Menu', icon: Coffee, to: '/admin/menu', roles: ['admin', 'shift_leader'] },
        { label: 'Quản lý lịch làm', icon: CalendarDays, to: '/admin/schedule', roles: ['admin', 'shift_leader'] },
        { label: 'Bảng lương NV', icon: CircleDollarSign, to: '/admin/payroll', roles: ['admin', 'shift_leader'] },
        { label: 'Lương của tôi', icon: CircleDollarSign, to: '/staff/payroll', roles: ['staff', 'shift_leader'] },
        { label: 'Chấm công', icon: CheckCircle2, to: '/staff/attendance', roles: ['staff', 'shift_leader'] },
        { label: 'Lịch làm', icon: Clock, to: '/staff/schedule', roles: ['staff'] },
    ];
    return items.filter(i => i.roles.includes(adminUser.value?.role));
});

const handleLogout = async () => {
    await logout();
    toast.success('Đã đăng xuất');
    router.push('/admin/login');
};

const isActive = (path) => route.path === path;
</script>

<template>
    <div class="al-container">
        <!-- Overlay for mobile -->
        <div 
            v-if="isMobile && isMobileMenuOpen" 
            class="al-overlay" 
            @click="closeMobileMenu"
        ></div>

        <!-- Sidebar -->
        <aside 
            class="al-sidebar" 
            :class="{ 
                'collapsed': isCollapsed && !isMobile, 
                'mobile-open': isMobile && isMobileMenuOpen,
                'mobile-hidden': isMobile && !isMobileMenuOpen
            }"
        >
            <div class="al-sidebar-logo">
                <img v-if="!isCollapsed || isMobile" src="../../../images/logo.png" alt="Colab" />
                <Coffee v-else class="al-logo-icon" :size="32" />
                
                <button v-if="!isMobile" class="al-toggle-btn" @click="toggleSidebar">
                    <PanelLeftClose v-if="!isCollapsed" :size="18" />
                    <PanelLeftOpen v-else :size="18" />
                </button>
                <button v-else class="al-close-mobile-btn" @click="closeMobileMenu">
                    <X :size="24" />
                </button>
            </div>

            <nav class="al-nav">
                <router-link 
                    v-for="item in navItems" 
                    :key="item.to" 
                    :to="item.to" 
                    class="al-nav-item"
                    :class="{ 'al-nav-item--active': isActive(item.to) }"
                    :title="isCollapsed && !isMobile ? item.label : ''"
                    @click="isMobile ? closeMobileMenu() : null"
                >
                    <component :is="item.icon" class="al-icon" :size="20" />
                    <span v-if="!isCollapsed || isMobile">{{ item.label }}</span>
                </router-link>
            </nav>

            <div class="al-sidebar-footer">
                <button class="al-logout-btn" @click="handleLogout" :title="isCollapsed && !isMobile ? 'Đăng xuất' : ''">
                    <LogOut :size="20" />
                    <span v-if="!isCollapsed || isMobile">Đăng xuất</span>
                </button>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="al-main">
            <header class="al-topbar">
                <div class="al-topbar-left">
                    <button v-if="isMobile" class="al-menu-btn" @click="isMobileMenuOpen = true">
                        <Menu :size="24" />
                    </button>
                    <h2 class="al-page-title">
                        <slot name="title">Tổng quan</slot>
                    </h2>
                </div>
                <div class="al-topbar-right">
                    <div class="al-user-info" v-if="adminUser">
                        <span class="al-user-name">{{ adminUser.name }}</span>
                        <div class="al-user-avatar">
                            {{ adminUser.name.charAt(0) }}
                        </div>
                    </div>
                </div>
            </header>
            <div class="al-content">
                <slot />
            </div>
        </main>
    </div>
</template>

<style scoped>
.al-container {
    display: flex;
    min-height: 100vh;
    background: #fcfdfc;
    color: #1a1a2e;
    font-family: 'Inter', sans-serif;
}

.al-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.4);
    backdrop-filter: blur(4px);
    z-index: 1000;
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.al-sidebar {
    width: 260px;
    background: white;
    border-right: 1px solid #f0f0f0;
    display: flex;
    flex-direction: column;
    position: sticky;
    top: 0;
    height: 100vh;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    z-index: 200;
}

.al-sidebar.collapsed {
    width: 80px;
}

.al-sidebar-logo {
    padding: 24px 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    height: 80px;
    position: relative;
}

.al-sidebar-logo img {
    height: 32px;
}

.al-logo-icon {
    color: #2D4F1E;
    margin-left: 8px;
}

.al-toggle-btn {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    border: 1.5px solid #f1f5f9;
    background: white;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    color: #64748b;
    transition: 0.2s;
}

.al-toggle-btn:hover {
    background: #f8fafc;
    border-color: #e2e8f0;
    color: #2D4F1E;
}

.collapsed .al-toggle-btn {
    position: absolute;
    right: -16px;
    top: 24px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    z-index: 10;
}

.al-close-mobile-btn {
    background: none;
    border: none;
    color: #64748b;
    cursor: pointer;
    padding: 4px;
}

.al-nav {
    flex: 1;
    padding: 0 16px;
    overflow-y: auto;
}

.al-nav-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    color: #64748b;
    text-decoration: none;
    border-radius: 12px;
    margin-bottom: 4px;
    font-weight: 600;
    font-size: 0.95rem;
    line-height: 1;
    transition: all 0.2s;
}

.al-nav-item span {
    display: inline-block;
    transform: translateY(-1.5px);
}

.al-nav-item:hover {
    background: #f1f5f9;
    color: #2D4F1E;
}

.al-nav-item--active, 
.al-nav-item--active:hover {
    background: #2D4F1E !important;
    color: white !important;
}

.al-icon {
    flex-shrink: 0;
}

.al-sidebar-footer {
    padding: 16px;
    border-top: 1px solid #f0f0f0;
}

.al-logout-btn {
    width: 100%;
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    background: #fff1f2;
    color: #e11d48;
    border: none;
    border-radius: 12px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s;
}

.al-logout-btn:hover {
    background: #ffe4e6;
}

.al-main {
    flex: 1;
    display: flex;
    flex-direction: column;
    min-width: 0;
    width: 100%;
    overflow-x: hidden;
}

.al-topbar {
    height: 70px;
    background: white;
    border-bottom: 1px solid #f0f0f0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 32px;
    position: sticky;
    top: 0;
    z-index: 100;
}

.al-topbar-left {
    display: flex;
    align-items: center;
    gap: 16px;
}

.al-menu-btn {
    background: none;
    border: none;
    color: #1a1a2e;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 4px;
}

.al-page-title {
    font-size: 1.25rem;
    font-weight: 800;
    color: #1a1a2e;
    margin: 0;
}

.al-topbar-right {
    display: flex;
    align-items: center;
}

.al-user-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.al-user-name {
    font-weight: 600;
    font-size: 0.9rem;
    color: #64748b;
}

.al-user-avatar {
    width: 36px;
    height: 36px;
    background: #2D4F1E;
    color: white;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 1rem;
}

.al-content {
    flex: 1;
    padding: 32px;
    overflow-y: auto;
}

@media (max-width: 768px) {
    .al-sidebar {
        position: fixed;
        left: 0;
        top: 0;
        bottom: 0;
        z-index: 1001;
        transform: translateX(-100%);
    }

    .al-sidebar.mobile-open {
        transform: translateX(0);
        box-shadow: 10px 0 30px rgba(0, 0, 0, 0.1);
    }

    .al-topbar {
        padding: 0 16px;
    }

    .al-content {
        padding: 16px;
    }

    .al-user-name {
        display: none;
    }
}
</style>

