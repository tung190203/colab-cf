<script setup>
import { ref, onMounted, onUnmounted, computed, nextTick } from 'vue';
import Echo from '../echo.js';
import { useBooking } from '../composables/useBooking';
import { useAdminAuth } from '../composables/useAdminAuth';
import { toast } from 'vue3-toastify';
import AdminLayout from './admin/AdminLayout.vue';
import ConfirmDialog from './ConfirmDialog.vue';
import { 
    RefreshCw, 
    Clock, 
    MapPin, 
    ChevronLeft, 
    ChevronRight, 
    CheckCircle2, 
    X, 
    Package,
    Phone
} from 'lucide-vue-next';

const { fetchListBooking, bookingList, formatCategoryName } = useBooking();
const { authHeader } = useAdminAuth();

const selectedBooking = ref(null);
let modalInstance = null;
const currentPage = ref(1);
const itemsPerPage = 12;
const loading = ref(true);
const serving = ref(false);
const canceling = ref(false);
const stockChecking = ref(false);
const stockCheck = ref(null);
const actionError = ref('');
const cancelConfirm = ref({ show: false, booking: null });

const totalPages = computed(() => Math.ceil(bookingList.value.length / itemsPerPage));
const paginatedBookingList = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage;
  return bookingList.value.slice(start, start + itemsPerPage);
});

const visiblePageItems = computed(() => buildPageItems(currentPage.value, totalPages.value));
const canServeSelectedBooking = computed(() => {
  return !stockChecking.value && stockCheck.value?.ok !== false;
});

function buildPageItems(current, total) {
  if (total <= 7) {
    return Array.from({ length: total }, (_, index) => ({ type: 'page', page: index + 1, key: `page-${index + 1}` }));
  }

  const pages = new Set([1, total, current - 1, current, current + 1]);
  if (current <= 3) [2, 3, 4].forEach(page => pages.add(page));
  if (current >= total - 2) [total - 3, total - 2, total - 1].forEach(page => pages.add(page));

  const sortedPages = [...pages].filter(page => page >= 1 && page <= total).sort((a, b) => a - b);
  const items = [];

  sortedPages.forEach((page, index) => {
    const previous = sortedPages[index - 1];
    if (index > 0 && page - previous > 1) {
      items.push({ type: 'ellipsis', key: `ellipsis-${previous}-${page}` });
    }
    items.push({ type: 'page', page, key: `page-${page}` });
  });

  return items;
}

// Modal logic
function openModal(booking) {
  selectedBooking.value = booking;
  actionError.value = '';
  stockCheck.value = null;
  checkBookingStock(booking.id);
  nextTick(() => {
    if (!modalInstance) {
      const modalEl = document.getElementById('bookingModal');
      // @ts-ignore
      modalInstance = new bootstrap.Modal(modalEl, {});
    }
    modalInstance.show();
  });
}

async function checkBookingStock(bookingId) {
  stockChecking.value = true;

  try {
    const res = await fetch(`/api/booking/${bookingId}/stock-check`, {
      headers: authHeader()
    });
    const data = await res.json().catch(() => ({}));

    if (selectedBooking.value?.id !== bookingId) return;

    if (res.ok) {
      stockCheck.value = data.stock_check;
      actionError.value = data.stock_check?.ok === false ? data.stock_check.message : '';
    } else {
      stockCheck.value = { ok: false, message: responseMessage(data, 'Không thể kiểm tra tồn kho') };
      actionError.value = stockCheck.value.message;
    }
  } catch (error) {
    if (selectedBooking.value?.id !== bookingId) return;
    stockCheck.value = { ok: false, message: 'Có lỗi xảy ra khi kiểm tra tồn kho' };
    actionError.value = stockCheck.value.message;
  } finally {
    if (selectedBooking.value?.id === bookingId) {
      stockChecking.value = false;
    }
  }
}

function responseMessage(data, fallback) {
  if (data?.errors) {
    return Object.values(data.errors).flat().join('\n');
  }

  return data?.message || fallback;
}

function removeBookingFromList(bookingId) {
  bookingList.value = bookingList.value.filter(b => b.id !== bookingId);
  if ((currentPage.value - 1) * itemsPerPage >= bookingList.value.length && currentPage.value > 1) {
    currentPage.value--;
  }
}

function goToPage(page) {
  if (page >= 1 && page <= totalPages.value) currentPage.value = page;
}

function formatBookingTime(start, end) {
  const startDate = new Date(start);
  const endDate = new Date(end);
  const optionsTime = { hour: 'numeric', minute: '2-digit', hour12: false };
  const optionsDate = { day: '2-digit', month: '2-digit', year: 'numeric' };
  return `${startDate.toLocaleTimeString('vi-VN', optionsTime)} - ${endDate.toLocaleTimeString('vi-VN', optionsTime)} | ${startDate.toLocaleDateString('vi-VN', optionsDate)}`;
}

async function markAsServed(bookingId) {
  if (serving.value || canceling.value) return;
  if (stockChecking.value) {
    actionError.value = 'Đang kiểm tra tồn kho, vui lòng chờ một chút';
    return;
  }
  if (stockCheck.value?.ok === false) {
    actionError.value = stockCheck.value.message || 'Không đủ nguyên vật liệu để phục vụ đơn';
    return;
  }

  serving.value = true;
  actionError.value = '';

  try {
    const res = await fetch(`/api/booking/mark-as-served`, {
      method: 'POST',
      headers: { 
        'Content-Type': 'application/json',
        ...authHeader()
      },
      body: JSON.stringify({ booking_id: bookingId }),
    });
    const data = await res.json().catch(() => ({}));
    
    if (res.ok) {
        toast.success('Đã xác nhận phục vụ!');
        removeBookingFromList(bookingId);
        if (modalInstance) modalInstance.hide();
    } else {
        actionError.value = responseMessage(data, 'Không thể xác nhận đơn');
        await checkBookingStock(bookingId);
        toast.error(actionError.value);
    }
  } catch (e) {
    actionError.value = 'Có lỗi xảy ra khi xác nhận';
    toast.error(actionError.value);
  } finally {
    serving.value = false;
  }
}

function requestCancelBooking(booking) {
  if (!booking || serving.value || canceling.value) return;
  actionError.value = '';
  cancelConfirm.value = { show: true, booking };
}

async function executeCancelBooking() {
  const booking = cancelConfirm.value.booking;
  if (!booking) return;

  canceling.value = true;
  actionError.value = '';

  try {
    const res = await fetch('/api/booking/cancel', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        ...authHeader()
      },
      body: JSON.stringify({
        booking_id: booking.id,
        note: 'Huỷ do không đủ nguyên vật liệu hoặc không thể phục vụ',
      }),
    });
    const data = await res.json().catch(() => ({}));

    if (res.ok) {
      toast.success('Đã huỷ đơn');
      removeBookingFromList(booking.id);
      cancelConfirm.value = { show: false, booking: null };
      if (modalInstance) modalInstance.hide();
    } else {
      actionError.value = responseMessage(data, 'Không thể huỷ đơn');
      toast.error(actionError.value);
    }
  } catch (error) {
    actionError.value = 'Có lỗi xảy ra khi huỷ đơn';
    toast.error(actionError.value);
  } finally {
    canceling.value = false;
  }
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

onMounted(async () => {
  loading.value = true;
  await fetchListBooking();
  loading.value = false;

  // Lắng nghe sự kiện real-time
  Echo.channel('bookings')
    .listen('.new-booking-created', (e) => {
      const exists = bookingList.value.some(b => b.id === e.booking.id);
      if (!exists) {
          bookingList.value.unshift(e.booking);
          if (currentPage.value !== 1) currentPage.value = 1;
          toast.info(`Đơn mới từ: ${e.booking.full_name}`);
      }
    });
});

onUnmounted(() => {
    Echo.leave('bookings');
});
</script>

<template>
  <AdminLayout>
    <template #title>Quản lý đơn hàng</template>

    <div class="nb-wrap">
      <!-- Toolbar/Stats -->
      <div class="nb-toolbar">
        <div class="nb-stats">
          <span class="nb-stats-dot"></span>
          <strong>{{ bookingList.length }}</strong> đơn đang chờ phục vụ
        </div>
        <div class="nb-refresh">
            <button @click="fetchListBooking" class="btn-refresh">
                <RefreshCw :size="16" class="me-2" />
                Làm mới
            </button>
        </div>
      </div>

      <!-- Grid -->
      <div v-if="loading" class="nb-loading">
        <div class="nb-spinner"></div>
      </div>

      <div v-else-if="bookingList.length === 0" class="nb-empty">
        <div class="nb-empty-icon"><Package :size="64" /></div>
        <h3>Hiện không có đơn mới</h3>
        <p>Hệ thống sẽ tự động cập nhật khi có khách đặt bàn.</p>
      </div>

      <div v-else class="nb-grid">
        <div v-for="(booking, index) in paginatedBookingList" :key="booking.id" class="nb-card" @click="openModal(booking)">
          <div class="nb-card-header">
            <span class="nb-id">#{{ (currentPage - 1) * itemsPerPage + index + 1 }}</span>
            <span class="nb-badge">Chờ phục vụ</span>
          </div>
          
          <div class="nb-card-body">
            <h4 class="nb-name">{{ booking.full_name }}</h4>
            <div class="nb-package">
                <span class="nb-pkg-tag">{{ booking.package.name }}</span>
                <span class="nb-pkg-cat">{{ booking.package.category }}</span>
            </div>

            <div class="nb-meta">
              <div class="nb-meta-item">
                <Clock :size="14" />
                {{ formatBookingTime(booking.start_time, booking.end_time) }}
              </div>
              <div v-if="booking.table" class="nb-meta-item nb-meta-item--highlight">
                <MapPin :size="14" />
                Bàn/Phòng: <strong>{{ booking.table.code }}</strong>
              </div>
            </div>
          </div>
          
          <div class="nb-card-footer">
            <span class="nb-payment">{{ formatPaymentMethod(booking.payment_method) }}</span>
            <span class="nb-price">{{ new Intl.NumberFormat('vi-VN').format(booking.total_price) }} ₫</span>
          </div>
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="totalPages > 1" class="nb-pagination">
        <button :disabled="currentPage === 1" @click="goToPage(currentPage - 1)" class="nb-page-btn">
            <ChevronLeft :size="18" />
        </button>
        <template v-for="item in visiblePageItems" :key="item.key">
        <span v-if="item.type === 'ellipsis'" class="nb-page-ellipsis">...</span>
        <button
          v-else
          @click="goToPage(item.page)"
          class="nb-page-btn" 
          :class="{ active: currentPage === item.page }"
        >{{ item.page }}</button>
        </template>
        <button :disabled="currentPage === totalPages" @click="goToPage(currentPage + 1)" class="nb-page-btn">
            <ChevronRight :size="18" />
        </button>
      </div>
    </div>

    <!-- Modal Chi Tiết -->
    <Teleport to="body">
      <div class="modal fade" id="bookingModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
          <div class="modal-content nb-modal">
            <div class="modal-header nb-modal-header">
              <h5 class="modal-title">CHI TIẾT ĐƠN #{{ selectedBooking?.id }}</h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body nb-modal-body">
              <div v-if="stockChecking" class="nb-stock-status nb-stock-status--checking">
                Đang kiểm tra nguyên vật liệu...
              </div>
              <div v-else-if="stockCheck?.ok" class="nb-stock-status nb-stock-status--ok">
                {{ stockCheck.message }}
              </div>
              <div v-if="actionError" class="nb-action-error">
                {{ actionError }}
              </div>
              <div class="row g-4">
                <div class="col-md-7">
                  <div class="nb-detail-box">
                    <label>Khách hàng</label>
                    <div class="nb-val-main">{{ selectedBooking?.full_name }}</div>
                    <div class="nb-val-sub"><Phone :size="14" class="me-1" /> {{ selectedBooking?.phone }}</div>
                  </div>

                  <div class="nb-detail-box mt-3">
                    <label>Dịch vụ đặt</label>
                    <div class="nb-val-main">{{ selectedBooking?.package.name }}</div>
                    <div class="nb-val-info">
                        <strong>Vị trí: {{ selectedBooking?.table?.code || 'Chưa chọn' }}</strong>
                    </div>
                    <div class="nb-val-sub"><Clock :size="14" class="me-1" /> {{ selectedBooking ? formatBookingTime(selectedBooking.start_time, selectedBooking.end_time) : '' }}</div>
                    
                    <div v-if="selectedBooking?.address" class="nb-val-address mt-2">
                        <MapPin :size="14" class="me-1" /> {{ selectedBooking.address }}
                    </div>
                    <div v-if="selectedBooking?.note" class="nb-val-note mt-2">
                        <em>{{ selectedBooking.note }}</em>
                    </div>
                  </div>
                </div>

                <div class="col-md-5">
                  <div class="nb-detail-box h-100">
                    <label>Dịch vụ thêm</label>
                    <div v-if="selectedBooking?.extras?.length" class="nb-extras-list">
                      <div v-for="extra in selectedBooking.extras" :key="extra.id" class="nb-extra-item">
                        <span>{{ extra.name }}</span>
                        <span class="nb-extra-qty">x{{ extra.pivot.quantity }}</span>
                      </div>
                    </div>
                    <div v-else class="nb-empty-small">Không có</div>

                    <div v-if="selectedBooking?.proof_image" class="mt-3">
                        <label>Minh chứng thanh toán</label>
                        <div class="nb-proof-wrap">
                            <img :src="'/storage/' + selectedBooking.proof_image" class="img-fluid rounded" />
                        </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="modal-footer nb-modal-footer">
              <button type="button" class="btn btn-secondary rounded-3" data-bs-dismiss="modal">Đóng</button>
              <button type="button" class="btn btn-outline-danger rounded-3 px-4 fw-bold d-flex align-items-center" :disabled="serving || canceling" @click="requestCancelBooking(selectedBooking)">
                <X :size="18" class="me-2" />
                {{ canceling ? 'Đang huỷ...' : 'Huỷ đơn' }}
              </button>
              <button type="button" class="btn btn-success rounded-3 px-4 fw-bold d-flex align-items-center" :disabled="serving || canceling || !canServeSelectedBooking" @click="markAsServed(selectedBooking.id)">
                <CheckCircle2 :size="18" class="me-2" />
                {{ stockChecking ? 'Đang kiểm tra...' : (serving ? 'Đang xác nhận...' : 'Xác nhận đã phục vụ') }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

    <ConfirmDialog
      :show="cancelConfirm.show"
      title="Huỷ đơn"
      :message="`Huỷ đơn #${cancelConfirm.booking?.id || ''}? Đơn sẽ rời khỏi danh sách chờ phục vụ và được lưu trạng thái đã huỷ.`"
      confirm-text="Huỷ đơn"
      cancel-text="Giữ lại"
      type="danger"
      @confirm="executeCancelBooking"
      @cancel="cancelConfirm = { show: false, booking: null }"
    />
  </AdminLayout>
</template>

<style scoped>
/* CSS giữ nguyên, chỉ chỉnh sửa icon SVG cũ sang Lucide components */
.nb-wrap { max-width: 1200px; margin: 0 auto; }

.nb-toolbar {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 24px; flex-wrap: wrap; gap: 12px;
}

.nb-stats {
    display: flex; align-items: center; gap: 8px;
    background: white; padding: 10px 18px; border-radius: 100px;
    font-size: 0.9rem; color: #444; box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}
.nb-stats-dot { width: 8px; height: 8px; background: #2D4F1E; border-radius: 50%; animation: pulse 2s infinite; }
@keyframes pulse { 0% { opacity: 1; transform: scale(1); } 50% { opacity: 0.4; transform: scale(1.2); } 100% { opacity: 1; transform: scale(1); } }

.btn-refresh {
    display: flex; align-items: center; justify-content: center;
    background: white; border: 1.5px solid #e0e6ed; border-radius: 10px;
    padding: 8px 16px; font-size: 0.85rem; font-weight: 600; color: #666;
    cursor: pointer; transition: all 0.2s;
}
.btn-refresh:hover { border-color: #2D4F1E; color: #2D4F1E; }

.nb-loading { display: flex; justify-content: center; padding: 60px; }
.nb-spinner { width: 40px; height: 40px; border: 3px solid #f0f0f0; border-top-color: #2D4F1E; border-radius: 50%; animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

.nb-empty { text-align: center; padding: 80px 20px; background: white; border-radius: 24px; box-shadow: 0 2px 12px rgba(0,0,0,0.05); }
.nb-empty-icon { color: #2D4F1E; margin-bottom: 16px; opacity: 0.3; display: flex; justify-content: center; }
.nb-empty h3 { color: #1a1a2e; font-weight: 700; margin-bottom: 8px; }
.nb-empty p { color: #888; }

.nb-action-error {
    margin-bottom: 16px;
    padding: 12px 14px;
    border: 1px solid #fecaca;
    border-radius: 8px;
    background: #fef2f2;
    color: #b91c1c;
    font-weight: 700;
    white-space: pre-line;
}

.nb-stock-status {
    margin-bottom: 12px;
    padding: 10px 14px;
    border-radius: 8px;
    font-weight: 700;
}

.nb-stock-status--checking {
    border: 1px solid #bfdbfe;
    background: #eff6ff;
    color: #1d4ed8;
}

.nb-stock-status--ok {
    border: 1px solid #bbf7d0;
    background: #f0fdf4;
    color: #15803d;
}

.nb-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px; }

.nb-card {
    background: white; border-radius: 20px; border: 1.5px solid transparent;
    padding: 20px; cursor: pointer; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 2px 12px rgba(0,0,0,0.05);
    display: flex; flex-direction: column;
}
.nb-card:hover { transform: translateY(-5px); border-color: #2D4F1E; box-shadow: 0 12px 24px rgba(45,79,30,0.12); }

.nb-card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.nb-id { font-weight: 800; color: #ddd; font-size: 1.2rem; }
.nb-badge { background: #fff8e6; color: #b38600; font-size: 0.7rem; font-weight: 700; padding: 4px 10px; border-radius: 100px; }

.nb-name { font-weight: 700; color: #1a1a2e; margin: 0 0 10px; font-size: 1.1rem; }
.nb-package { display: flex; align-items: center; gap: 8px; margin-bottom: 16px; }
.nb-pkg-tag { background: #2D4F1E; color: white; font-size: 0.7rem; font-weight: 700; padding: 3px 10px; border-radius: 6px; }
.nb-pkg-cat { font-size: 0.75rem; color: #888; text-transform: uppercase; letter-spacing: 0.05em; }

.nb-meta { display: flex; flex-direction: column; gap: 8px; flex: 1; }
.nb-meta-item { display: flex; align-items: center; gap: 8px; font-size: 0.85rem; color: #666; }
.nb-meta-item--highlight { color: #2D4F1E; background: rgba(45,79,30,0.06); padding: 4px 10px; border-radius: 8px; margin-top: 4px; }

.nb-card-footer {
    margin-top: 16px; padding-top: 16px; border-top: 1px solid #f5f5f5;
    display: flex; justify-content: space-between; align-items: center;
}
.nb-payment { font-size: 0.8rem; color: #888; font-weight: 600; }
.nb-price { font-weight: 800; color: #1a1a2e; font-size: 1rem; }

.nb-pagination { display: flex; justify-content: center; gap: 8px; margin-top: 40px; }
.nb-page-ellipsis { width: 28px; height: 38px; display: flex; align-items: center; justify-content: center; color: #94a3b8; font-weight: 800; }
.nb-page-btn {
    width: 38px; height: 38px; border-radius: 10px; border: 1px solid #e0e6ed;
    background: white; cursor: pointer; font-weight: 700; color: #666; transition: all 0.2s;
    display: flex; align-items: center; justify-content: center;
}
.nb-page-btn:hover:not(:disabled) { border-color: #2D4F1E; color: #2D4F1E; background: #f0f4f0; }
.nb-page-btn.active { background: #2D4F1E; color: white; border-color: #2D4F1E; }
.nb-page-btn:disabled { opacity: 0.4; cursor: not-allowed; }

/* Modal */
.nb-modal { border-radius: 24px; overflow: hidden; border: none; }
.nb-modal-header { background: #2D4F1E; color: white; padding: 20px 28px; border: none; }
.nb-modal-body { padding: 28px; background: #fcfdfc; }
.nb-modal-footer { padding: 20px 28px; border: none; background: white; }

.nb-detail-box { background: white; padding: 20px; border-radius: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
.nb-detail-box label { display: block; font-size: 0.75rem; font-weight: 700; color: #888; text-transform: uppercase; margin-bottom: 8px; letter-spacing: 0.05em; }

.nb-val-main { font-size: 1.3rem; font-weight: 800; color: #1a1a2e; margin-bottom: 4px; }
.nb-val-sub { font-size: 0.9rem; color: #666; font-weight: 500; display: flex; align-items: center; }
.nb-val-info { margin: 8px 0; color: #2D4F1E; font-size: 1rem; }
.nb-val-address { font-size: 0.85rem; color: #555; background: #f8fafb; padding: 10px; border-radius: 8px; display: flex; align-items: center; }
.nb-val-note { font-size: 0.85rem; color: #b38600; background: #fffdf5; padding: 10px; border-radius: 8px; }

.nb-extras-list { display: flex; flex-direction: column; gap: 8px; }
.nb-extra-item { display: flex; justify-content: space-between; padding: 10px 14px; background: #f8fafb; border-radius: 10px; font-size: 0.9rem; font-weight: 600; }
.nb-extra-qty { color: #2D4F1E; }
.nb-empty-small { color: #aaa; font-style: italic; font-size: 0.85rem; }

.nb-proof-wrap { margin-top: 10px; background: #f0f0f0; border-radius: 12px; overflow: hidden; }
.nb-proof-wrap img { width: 100%; transition: transform 0.3s; cursor: zoom-in; }
.nb-proof-wrap img:hover { transform: scale(1.05); }

@media (max-width: 600px) {
    .nb-grid { grid-template-columns: 1fr; }
    .nb-modal-body { padding: 16px; }
}
</style>
