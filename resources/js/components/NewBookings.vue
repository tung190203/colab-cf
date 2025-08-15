<script setup>
import { ref, onMounted, computed, nextTick } from 'vue';
import Echo from '../echo.js';
import { useBooking } from '../composables/useBooking';
import { toast } from 'vue3-toastify';

const { fetchListBooking, bookingList } = useBooking();
const selectedBooking = ref(null);
const notificationSound = ref(null);
let modalInstance = null;
const currentPage = ref(1);
const itemsPerPage = 30;
const totalPages = computed(() => Math.ceil(bookingList.value.length / itemsPerPage));
const paginatedBookingList = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage;
  const end = start + itemsPerPage;
  return bookingList.value.slice(start, end);
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
  if (page >= 1 && page <= totalPages.value) {
    currentPage.value = page;
  }
}

function formatBookingTime(start, end) {
  const startDate = new Date(start);
  const endDate = new Date(end);

  const optionsTime = { hour: 'numeric', minute: '2-digit', hour12: true };
  const optionsDate = { day: '2-digit', month: '2-digit', year: 'numeric' };

  const startTimeStr = startDate.toLocaleTimeString('en-US', optionsTime);
  const endTimeStr = endDate.toLocaleTimeString('en-US', optionsTime);
  const dateStr = startDate.toLocaleDateString('en-GB', optionsDate);

  return `${startTimeStr} - ${endTimeStr} - ${dateStr}`;
}

function formatServedStatus(isServed) {
  return isServed === 0 ? 'Chưa phục vụ' : 'Đã phục vụ';
}

function markAsServed(bookingId) {
  fetch(`/api/booking/mark-as-served`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({ booking_id: bookingId }),
  });
  toast.success('Đánh dấu đã phục vụ thành công!');
  bookingList.value = bookingList.value.filter(booking => booking.id !== bookingId);
  if (modalInstance) {
    modalInstance.hide();
  }
  if ((currentPage.value - 1) * itemsPerPage >= bookingList.value.length && currentPage.value > 1) {
    currentPage.value--;
  }
}

onMounted(async () => {
  await fetchListBooking();

  Echo.channel('bookings')
    .listen('.new-booking-created', (e) => {
      bookingList.value.unshift(e.booking);
      if (currentPage.value !== 1) currentPage.value = 1;

      toast.info(`Đã có booking mới từ: ${e.booking.full_name}`);

      if (notificationSound.value) {
        console.log('Playing notification sound');
        notificationSound.value.play()
          .catch(error => {
            console.error('Error playing notification sound:', error);
          });
      }
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
              <span :class="{
                'bg-warning text-dark': booking.is_served === 0,
              }" class="badge">
                {{ formatServedStatus(booking.is_served) }}
              </span>
            </div>
            <h5 class="card-title text-base">{{ booking.full_name }}</h5>
            <p class="card-text text-capitalize">Gói: {{ booking.package.name }} ({{ booking.package.category }})</p>
            <p class="card-text text-muted small" style="font-size: 12.5px;">
              {{ formatBookingTime(booking.start_time, booking.end_time) }}
            </p>
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
        <li class="page-item" v-for="page in totalPages" :key="page" :class="{ active: currentPage === page }">
          <button class="page-link" @click="goToPage(page)">{{ page }}</button>
        </li>
        <li class="page-item" :class="{ disabled: currentPage === totalPages }">
          <button class="page-link" @click="goToPage(currentPage + 1)">Next</button>
        </li>
      </ul>
    </nav>

    <!-- Modal Bootstrap -->
    <div class="modal fade" id="bookingModal" tabindex="-1" aria-labelledby="bookingModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-lg"> <!-- modal-lg cho to hơn -->
        <div class="modal-content">
          <div class="modal-header bg-success text-white">
            <h5 class="modal-title" id="bookingModalLabel">Chi tiết đơn đặt</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
              <!-- Gói đặt -->
              <div class="col-md-6">
                <div class="card shadow-sm p-2">
                  <h6 class="fw-bold mb-1">Gói đặt</h6>
                  <p class="mb-0 text-capitalize">{{ selectedBooking?.package.category }}</p>
                </div>
              </div>

              <div class="col-md-6">
                <div class="card shadow-sm p-2">
                  <h6 class="fw-bold mb-1">Bàn</h6>
                  <p class="mb-0">{{ selectedBooking?.table.code }}</p>
                </div>
              </div>
              <!-- Số điện thoại -->
              <div class="col-md-6">
                <div class="card shadow-sm p-2">
                  <h6 class="fw-bold mb-1">Tên người đặt</h6>
                  <p class="mb-0">{{ selectedBooking?.full_name || 'N/A' }}</p>
                </div>
              </div>
              <div class="col-md-6">
                <div class="card shadow-sm p-2">
                  <h6 class="fw-bold mb-1">Số điện thoại</h6>
                  <p class="mb-0">{{ selectedBooking?.phone || 'N/A' }}</p>
                </div>
              </div>

              <!-- Thời gian -->
              <div class="col-md-12">
                <div class="card shadow-sm p-2">
                  <h6 class="fw-bold mb-1">Thời gian</h6>
                  <p class="mb-0">{{ selectedBooking ? formatBookingTime(selectedBooking.start_time,
                    selectedBooking.end_time) : '' }}</p>
                </div>
              </div>

              <!-- Extras -->
              <div class="col-12">
                <div class="card shadow-sm p-3">
                  <h6 class="fw-bold mb-2">Dịch vụ thêm</h6>
                  <div v-if="selectedBooking?.extras?.length">
                    <ul class="list-group list-group-flush">
                      <li v-for="extra in selectedBooking.extras" :key="extra.id"
                        class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                          <strong>{{ extra.name }}</strong> ({{ extra.category }})
                        </div>
                        <div>
                          x{{ extra.pivot.quantity }}
                        </div>
                      </li>
                    </ul>
                  </div>
                  <div v-else class="text-muted">
                    Không có dịch vụ thêm.
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
            <button type="button" class="btn btn-success"
              @click="markAsServed(selectedBooking.id)">Đánh dấu là đã phục vụ</button>
          </div>
        </div>
      </div>
    </div>
  </div>
  <audio ref="notificationSound" src="/sounds/notification.mp3" preload="auto"></audio>
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
