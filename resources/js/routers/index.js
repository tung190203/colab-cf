import { createRouter, createWebHistory } from 'vue-router';
import PackageSelect from '../components/PackageSelect.vue';
import TableSelect from '../components/TableSelect.vue';
import ExtrasSelect from '../components/ExtrasSelect.vue';
import BookingSummary from '../components/BookingSummary.vue';
import StatusPage from '../components/StatusPage.vue';
import VietQRPage from '../components/VietQRPage.vue';

const routes = [
  { path: '/', component: PackageSelect, name: 'PackageSelect' },
  { path: '/table', component: TableSelect, name: 'TableSelect' },
  { path: '/extras', component: ExtrasSelect, name: 'ExtrasSelect' },
  { path: '/summary', component: BookingSummary, name: 'BookingSummary' },
  { path: '/status', component: StatusPage, name: 'StatusPage' },
  { path: '/transfer/:bookingId', component: VietQRPage, name: 'VietQRPage' },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

export default router;
