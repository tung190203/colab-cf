import { createRouter, createWebHistory } from 'vue-router';
import PackageSelect from '../components/PackageSelect.vue';
import TableSelect from '../components/TableSelect.vue';
import ExtrasSelect from '../components/ExtrasSelect.vue';
import BookingSummary from '../components/BookingSummary.vue';
import StatusPage from '../components/StatusPage.vue';
import VietQRPage from '../components/VietQRPage.vue';
import Auth from '../components/Auth.vue';
import NewBookings from '../components/NewBookings.vue';
import AddMember from '../components/AddMember.vue';
import ListMember from '../components/ListMember.vue';

const routes = [
  { path: '/', component: Auth, name: 'Auth' },
  { path: '/package', component: PackageSelect, name: 'PackageSelect' },
  { path: '/table', component: TableSelect, name: 'TableSelect' },
  { path: '/extras', component: ExtrasSelect, name: 'ExtrasSelect' },
  { path: '/summary', component: BookingSummary, name: 'BookingSummary' },
  { path: '/status', component: StatusPage, name: 'StatusPage' },
  { path: '/transfer/:bookingId', component: VietQRPage, name: 'VietQRPage' },
  { path: '/new-bookings', component: NewBookings, name: 'NewBookings' },
  { path: '/add-member', component: AddMember, name: 'AddMember' },
  { path: '/list-member', component: ListMember, name: 'ListMember' },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

router.beforeEach((to, from, next) => {
  if (to.path !== '/' && to.path !== '/new-bookings' && to.path !== '/add-member' && to.path !== '/list-member') {
    const name = sessionStorage.getItem('customer_name');
    const phone = sessionStorage.getItem('customer_phone');

    if (!name || !phone) {
      return next('/');
    }
  }
  next();
});

export default router;
