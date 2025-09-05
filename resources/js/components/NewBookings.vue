<script setup>
import { ref, onMounted, computed, nextTick } from 'vue';
import Echo from '../echo.js';
import { useBooking } from '../composables/useBooking';
import { toast } from 'vue3-toastify';

const { fetchListBooking, bookingList, formatCategoryName } = useBooking();
const selectedBooking = ref(null);
let modalInstance = null;
const currentPage = ref(1);
const itemsPerPage = 30;
const totalPages = computed(() => Math.ceil(bookingList.value.length / itemsPerPage));
const paginatedBookingList = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage;
  return bookingList.value.slice(start, start + itemsPerPage);
});

// Modal
function openModal(booking) {
  selectedBooking.value = booking;
  nextTick(() => {
    if (!modalInstance) {
      const modalEl = document.getElementById('bookingModal');
      modalInstance = new bootstrap.Modal(modalEl, {});
    }
    modalInstance.show();
  });
}

function goToPage(page) {
  if (page >= 1 && page <= totalPages.value) currentPage.value = page;
}

function formatBookingTime(start, end) {
  const startDate = new Date(start);
  const endDate = new Date(end);
  const optionsTime = { hour: 'numeric', minute: '2-digit', hour12: true };
  const optionsDate = { day: '2-digit', month: '2-digit', year: 'numeric' };
  return `${startDate.toLocaleTimeString('en-US', optionsTime)} - ${endDate.toLocaleTimeString('en-US', optionsTime)} - ${startDate.toLocaleDateString('en-GB', optionsDate)}`;
}

function formatServedStatus(isServed) {
  return isServed === 0 ? 'Chưa phục vụ' : 'Đã phục vụ';
}

function markAsServed(bookingId) {
  fetch(`/api/booking/mark-as-served`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ booking_id: bookingId }),
  });
  toast.success('Đánh dấu đã phục vụ thành công!');
  bookingList.value = bookingList.value.filter(b => b.id !== bookingId);
  if (modalInstance) modalInstance.hide();
  if ((currentPage.value - 1) * itemsPerPage >= bookingList.value.length && currentPage.value > 1) currentPage.value--;
}

function formatPaymentMethod(method) {
  switch (method) {
    case 'cash': return 'Tiền mặt';
    case 'transfer': return 'Chuyển khoản';
    case 'card': return 'Thẻ tín dụng';
    case 'momo': return 'Momo';
    default: return 'Không xác định';
  }
}

// Request notification permission
async function requestNotificationPermission() {
  if ('Notification' in window && Notification.permission !== 'granted') {
    const permission = await Notification.requestPermission();
    if (permission === 'granted') console.log('Notification permission granted.');
  }
}

onMounted(async () => {
  requestNotificationPermission();
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/firebase-messaging-sw.js')
      .then(reg => console.log('Service Worker registered:', reg));
  }

  await fetchListBooking();
  Echo.channel('bookings')
    .listen('.new-booking-created', (e) => {
      bookingList.value.unshift(e.booking);
      if (currentPage.value !== 1) currentPage.value = 1;
      toast.info(`Đã có booking mới từ: ${e.booking.full_name}`);
      navigator.serviceWorker.getRegistration().then(reg => {
        if (reg && Notification.permission === 'granted') {
          reg.showNotification('Booking mới', {
            body: `Bạn có booking mới từ: ${e.booking.full_name}`,
            icon: '/icon-192x192.png',
            data: '/new-bookings',
          });
        }
      });
    });
});
</script>

<template>
  <div class="container py-4">
    <h2 class="mb-4">Danh sách Booking</h2>
    <div class="row g-3">
      <div v-if="bookingList.length === 0" class="text-center text-muted py-5">
        Không có booking nào hiện tại.
      </div>
      <div v-for="(booking, index) in paginatedBookingList" :key="booking.id" class="col-md-4">
        <div class="card h-100 shadow-sm cursor-pointer" @click="openModal(booking)">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <span class="fw-bold">#{{ (currentPage - 1) * itemsPerPage + index + 1 }}</span>
              <span :class="{ 'bg-warning text-dark': booking.is_served === 0 }" class="badge">
                {{ formatServedStatus(booking.is_served) }}
              </span>
            </div>
            <h5 class="card-title">{{ booking.full_name }}</h5>
            <p class="card-text text-capitalize">Gói: {{ booking.package.name }} ({{ booking.package.category }})</p>
            <p class="card-text text-muted small">{{ formatBookingTime(booking.start_time, booking.end_time) }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Pagination -->
    <nav v-if="totalPages > 1" class="mt-4">
      <ul class="pagination justify-content-center">
        <li class="page-item" :class="{ disabled: currentPage === 1 }">
          <button class="page-link" @click="goToPage(currentPage - 1)">Previous</button>
        </li>
        <li v-for="page in totalPages" :key="page" :class="{ active: currentPage === page }">
          <button class="page-link" @click="goToPage(page)">{{ page }}</button>
        </li>
        <li class="page-item" :class="{ disabled: currentPage === totalPages }">
          <button class="page-link" @click="goToPage(currentPage + 1)">Next</button>
        </li>
      </ul>
    </nav>

    <!-- Modal -->
    <div class="modal fade" id="bookingModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
          <div class="modal-header bg-success text-white">
            <h5 class="modal-title">Chi tiết đơn đặt</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div v-if="selectedBooking">
              <p><strong>Tên:</strong> {{ selectedBooking.full_name }}</p>
              <p><strong>Số điện thoại:</strong> {{ selectedBooking.phone || 'N/A' }}</p>
              <p><strong>Gói:</strong> {{ selectedBooking.package.name }}</p>
              <p><strong>Thời gian:</strong> {{ formatBookingTime(selectedBooking.start_time, selectedBooking.end_time)
                }}</p>
              <p><strong>Phương thức thanh toán:</strong> {{ formatPaymentMethod(selectedBooking.payment_method) }}</p>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
            <button type="button" class="btn btn-success" @click="markAsServed(selectedBooking.id)">Đánh dấu đã phục
              vụ</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.pagination .page-item.active .page-link {
  background-color: #20451F;
  border-color: #20451F;
  color: white;
}

.pagination .page-link {
  color: #555555;
}

.pagination .page-link:hover {
  color: #20451F;
}
</style>
