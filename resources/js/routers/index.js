import { createRouter, createWebHistory } from 'vue-router';
import Welcome from '../components/Welcome.vue';
import BookingMain from '../components/BookingMain.vue';
import BookingSummary from '../components/BookingSummary.vue';
import StatusPage from '../components/StatusPage.vue';
import VietQRPage from '../components/VietQRPage.vue';
import Auth from '../components/Auth.vue';
import NewBookings from '../components/NewBookings.vue';
import ManualPosOrders from '../components/ManualPosOrders.vue';
import AddMember from '../components/AddMember.vue';
import ListMember from '../components/ListMember.vue';

// Admin components
import AdminLogin from '../components/admin/AdminLogin.vue';
import AdminDashboard from '../components/admin/AdminDashboard.vue';
import AdminStaffList from '../components/admin/AdminStaffList.vue';
import AdminSchedule from '../components/admin/AdminSchedule.vue';
import AdminPayroll from '../components/admin/AdminPayroll.vue';
import AdminOrders from '../components/admin/AdminOrders.vue';
import AdminMenu from '../components/admin/AdminMenu.vue';
import AdminBookingSetup from '../components/admin/AdminBookingSetup.vue';
import AdminAttendance from '../components/admin/AdminAttendance.vue';
import AdminCustomerStats from '../components/admin/AdminCustomerStats.vue';
import AdminAuditLogs from '../components/admin/AdminAuditLogs.vue';
import AdminPenaltyRules from '../components/admin/AdminPenaltyRules.vue';
import AdminMaterials from '../components/admin/AdminMaterials.vue';
import AdminRecipes from '../components/admin/AdminRecipes.vue';
import ShiftHandover from '../components/ShiftHandover.vue';

// Staff components
import StaffAttendance from '../components/staff/StaffAttendance.vue';
import StaffSchedule from '../components/staff/StaffSchedule.vue';
import StaffPayroll from '../components/staff/StaffPayroll.vue';
import QRCheckIn from '../components/staff/QRCheckIn.vue';

const routes = [
  // ─── Public routes (customer flow) ─────────────────────────────────────────
  { path: '/', component: Welcome, name: 'Welcome' },
  { path: '/login', component: Auth, name: 'Auth' },
  { path: '/package', component: BookingMain, name: 'BookingMain' },
  { path: '/table', redirect: '/package' },
  { path: '/extras', redirect: '/package' },
  { path: '/summary', component: BookingSummary, name: 'BookingSummary' },
  { path: '/status', component: StatusPage, name: 'StatusPage' },
  { path: '/transfer/:bookingId', component: VietQRPage, name: 'VietQRPage' },

  // ─── Admin/Staff login ───────────────────────────────────────────────────────
  { path: '/admin/login', component: AdminLogin, name: 'AdminLogin', meta: { public: true } },

  // ─── Admin only routes ───────────────────────────────────────────────────────
  { path: '/admin', redirect: '/admin/dashboard' },
  { path: '/admin/dashboard', component: AdminDashboard, name: 'AdminDashboard', meta: { requiresAuth: true, roles: ['admin', 'shift_leader'] } },
  { path: '/admin/staff', component: AdminStaffList, name: 'AdminStaffList', meta: { requiresAuth: true, roles: ['admin', 'shift_leader'] } },
  { path: '/admin/customer-stats', component: AdminCustomerStats, name: 'AdminCustomerStats', meta: { requiresAuth: true, roles: ['admin'] } },
  { path: '/admin/schedule', component: AdminSchedule, name: 'AdminSchedule', meta: { requiresAuth: true, roles: ['admin', 'shift_leader'] } },
  { path: '/admin/attendance', component: AdminAttendance, name: 'AdminAttendance', meta: { requiresAuth: true, roles: ['admin', 'shift_leader'] } },
  { path: '/admin/audit-logs', component: AdminAuditLogs, name: 'AdminAuditLogs', meta: { requiresAuth: true, roles: ['admin'] } },
  { path: '/admin/penalty-rules', component: AdminPenaltyRules, name: 'AdminPenaltyRules', meta: { requiresAuth: true, roles: ['admin'] } },
  { path: '/admin/payroll', component: AdminPayroll, name: 'AdminPayroll', meta: { requiresAuth: true, roles: ['admin', 'shift_leader'] } },
  { path: '/admin/menu', component: AdminMenu, name: 'AdminMenu', meta: { requiresAuth: true, roles: ['admin', 'shift_leader'] } },
  { path: '/admin/materials', component: AdminMaterials, name: 'AdminMaterials', meta: { requiresAuth: true, roles: ['admin', 'shift_leader'] } },
  { path: '/admin/recipes', component: AdminRecipes, name: 'AdminRecipes', meta: { requiresAuth: true, roles: ['admin', 'shift_leader'] } },
  { path: '/admin/shift-handovers', component: ShiftHandover, name: 'AdminShiftHandovers', meta: { requiresAuth: true, roles: ['admin', 'shift_leader'] } },
  { path: '/admin/booking-setup', component: AdminBookingSetup, name: 'AdminBookingSetup', meta: { requiresAuth: true, roles: ['admin', 'shift_leader'] } },

  // ─── Shared admin+staff routes ───────────────────────────────────────────────
  { path: '/staff', redirect: '/staff/dashboard' },
  { path: '/staff/dashboard', component: AdminDashboard, name: 'StaffDashboard', meta: { requiresAuth: true, roles: ['admin', 'staff', 'shift_leader'] } },
  { path: '/staff/attendance', component: StaffAttendance, name: 'StaffAttendance', meta: { requiresAuth: true, roles: ['staff', 'shift_leader'] } },
  { path: '/staff/schedule', component: StaffSchedule, name: 'StaffSchedule', meta: { requiresAuth: true, roles: ['admin', 'staff', 'shift_leader'] } },
  { path: '/staff/payroll', component: StaffPayroll, name: 'StaffPayroll', meta: { requiresAuth: true, roles: ['admin', 'staff', 'shift_leader'] } },
  { path: '/staff/shift-handover', component: ShiftHandover, name: 'StaffShiftHandover', meta: { requiresAuth: true, roles: ['admin', 'staff', 'shift_leader'] } },
  { path: '/staff/manual-orders', component: ManualPosOrders, name: 'ManualPosOrders', meta: { requiresAuth: true, roles: ['admin', 'staff', 'shift_leader'] } },
  { path: '/checkin', component: QRCheckIn, name: 'QRCheckIn', meta: { requiresAuth: true, roles: ['staff', 'shift_leader'] } },

  // ─── Orders (Admin/Staff) ──────────────────────────────────────────────────
  { path: '/admin/orders', component: AdminOrders, name: 'AdminOrders', meta: { requiresAuth: true, roles: ['admin'] } },
  { path: '/staff/orders', component: NewBookings, name: 'NewBookings', meta: { requiresAuth: true, roles: ['staff', 'shift_leader'] } },

  // ─── Members (Admin Only) ───────────────────────────────────────────────────
  { path: '/admin/members/list', component: ListMember, name: 'ListMember', meta: { requiresAuth: true, roles: ['admin'] } },
  { path: '/admin/members/add', component: AddMember, name: 'AddMember', meta: { requiresAuth: true, roles: ['admin'] } },

  // ─── Shared: both customers and admin/staff can access ──────────────────────
  { path: '/new-bookings', redirect: '/admin/orders' },
  { path: '/add-member', redirect: '/admin/members/add' },
  { path: '/list-member', redirect: '/admin/members/list' },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

router.beforeEach((to, from, next) => {
  // ─── Handle admin/staff protected routes ────────────────────────────────────
  if (to.meta.requiresAuth) {
    const token = localStorage.getItem('admin_token');
    const user = JSON.parse(localStorage.getItem('admin_user') || 'null');

    if (!token || !user) {
      return next('/admin/login');
    }

    const allowedRoles = to.meta.roles || [];
    if (allowedRoles.length > 0 && !allowedRoles.includes(user.role)) {
      // Admin can see staff pages, staff redirected to their own dashboard
      if (user.role === 'staff') {
        return next('/staff/dashboard');
      }
      return next('/admin/dashboard');
    }

    return next();
  }

  // ─── Handle legacy customer routes ──────────────────────────────────────────
  const publicPaths = ['/', '/login', '/new-bookings', '/add-member', '/list-member', '/admin/login'];
  const publicNames = ['Welcome', 'Auth', 'AdminLogin'];

  if (publicNames.includes(to.name) || publicPaths.includes(to.path) || to.meta.public) {
    return next();
  }

  const name = sessionStorage.getItem('customer_name');
  const phone = sessionStorage.getItem('customer_phone');

  if (!name || !phone) {
    return next('/login');
  }

  next();
});

export default router;
