<script setup>
import { useRouter } from 'vue-router';
import { useBooking } from '../composables/useBooking';
import { toast } from 'vue3-toastify'
import { ref } from 'vue';

const router = useRouter();

const {
  selectedPackage,
  selectedTable,
  extras,
  total,
  formatVND,
  start_time,
  end_time,
  name,
  phone,
  currentBookingId
} = useBooking();

const note = ref('');
const address = ref('');

function toVietnamDatetime(localDateTimeStr) {
  const [date, time] = localDateTimeStr.split('T');
  return `${date} ${time}:00`;
}

const isSubmitting = ref(false);

async function pay(method) {
  if (isSubmitting.value) return;
  isSubmitting.value = true;
  
  try {
    if (currentBookingId.value) {
      if (method === 'transfer') {
        router.push(`/transfer/${currentBookingId.value}`);
        return;
      }
      if (method === 'cash' || method === 'momo') {
        router.push('/status');
        return;
      }
    }

    const payload = {
      package_id: selectedPackage.value.id,
      table: selectedTable.value,
      start_time: toVietnamDatetime(start_time.value),
      end_time: toVietnamDatetime(end_time.value),
      extras: extras.value.map((e) => ({
        id: e.id,
        quantity: e.quantity || 1,
        free_applied: e.freeApplied || 0,
      })),
      payment_method: method,
      customer_name: name.value,
      customer_phone: phone.value,
      mode_booking:
        selectedPackage.value.category === 'basic'
          ? 'seat'
          : selectedPackage.value.category === 'vip'
            ? 'room'
            : selectedPackage.value.category === 'ship'
              ? 'order'
              : null,

      note: note.value || null,
      address: address.value || null,
    };

    const res = await fetch('/api/booking', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });

    if (!res.ok) {
      isSubmitting.value = false; // Mở khóa nếu lỗi để người dùng thử lại
      console.error('Payment error:', res.status, res.statusText);
      let errorMessage = `Lỗi thanh toán (status ${res.status})`;
      try {
        const errorData = await res.json();
        toast.error(`${errorData.message || 'Vui lòng thử lại sau'}`);
        if (errorData.message) errorMessage = errorData.message;
      } catch {
        const text = await res.text();
        toast.error(`Lỗi thanh toán: ${text || 'Vui lòng thử lại sau'}`);
      }
      throw new Error(errorMessage);
    }

    const data = await res.json();
    currentBookingId.value = data.booking.id;

    if (method === 'momo' && data.payUrl) {
      window.location.href = data.payUrl;
      return;
    }
    if (method === 'transfer') {
      router.push(`/transfer/${data.booking.id}`);
      return;
    }
    router.push('/status');

  } catch (error) {
    isSubmitting.value = false;
    console.error('Payment error:', error);
  }
}

function formatTimeRange(start, end) {
  const o = { hour: 'numeric', minute: '2-digit', hour12: true };
  const s = new Date(start), e = new Date(end);

  const timeRange = `${s.toLocaleTimeString('en-US', o)} - ${e.toLocaleTimeString('en-US', o)}`;
  const day = s.toLocaleDateString('vi-VN');

  return { timeRange, day };
}

function callHotline() {
  window.location.href = 'tel:0988992268';
}
</script>

<template>
  <div class="booking-summary-page">
    <!-- Header -->
    <header class="booking-header sticky-top py-3 mb-4">
      <div class="container d-flex align-items-center px-4">
        <button class="header-back-btn me-4" @click="router.push('/package')">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-chevron-left" viewBox="0 0 16 16">
            <path fill-rule="evenodd" d="M11.354 1.646a.5.5 0 0 1 0 .708L5.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0z"/>
          </svg>
        </button>
        <div class="header-brand">
          <img src="../../images/logo.png" alt="logo" class="header-logo" />
        </div>
      </div>
    </header>

    <div class="container pb-5">
      <div class="card section-card mb-4">
        <div class="card-body p-4">
          <h3 class="section-title mb-4">XÁC NHẬN ĐẶT CHỖ</h3>
          
          <div class="summary-list">
            <div class="summary-item">
              <span class="label">Gói</span>
              <span class="value fw-bold">{{ selectedPackage?.name ?? '-' }}</span>
            </div>
            <div class="summary-item">
              <span class="label">Vị trí ngồi</span>
              <span class="value fw-bold">{{ selectedTable ?? '-' }}</span>
            </div>
            <div class="summary-item">
              <span class="label">Ngày</span>
              <span class="value">
                <template v-if="start_time && end_time">
                  {{ formatTimeRange(start_time, end_time).day }}
                </template>
                <template v-else>-</template>
              </span>
            </div>
            <div class="summary-item">
              <span class="label">Thời gian</span>
              <span class="value">
                <template v-if="start_time && end_time">
                  {{ formatTimeRange(start_time, end_time).timeRange }}
                </template>
                <template v-else>-</template>
              </span>
            </div>
            <div class="summary-item">
              <span class="label">Người đặt</span>
              <span class="value fw-semibold text-dark">{{ name }}</span>
            </div>
            <div class="summary-item">
              <span class="label">SĐT</span>
              <span class="value">{{ phone }}</span>
            </div>
            
            <div class="summary-item highlight mt-3 border-top pt-3">
              <span class="label">Giá gói</span>
              <span class="value">{{ formatVND(selectedPackage?.price) ?? '-' }}</span>
            </div>

            <div v-if="extras.length" class="extras-list mt-2">
              <div v-for="(e, i) in extras" :key="i" class="summary-item extra-item">
                <span class="label ms-2 text-muted">{{ e.name }} x{{ e.quantity }}</span>
                <span class="value" :class="{ 'text-success': e.totalPrice === 0 }">
                  {{ e.totalPrice > 0 ? formatVND(e.totalPrice) : 'Miễn phí' }}
                </span>
              </div>
            </div>

            <div class="summary-item total-item mt-4 border-top pt-3">
              <span class="label fw-bold fs-5">TỔNG</span>
              <span class="value fw-bold fs-4 text-success">{{ formatVND(total) }}</span>
            </div>
          </div>

          <div v-if="selectedPackage?.category === 'ship'" class="mt-4">
            <label class="form-label fw-bold small text-muted">ĐỊA CHỈ GIAO HÀNG</label>
            <textarea v-model="address" class="form-control auth-input" rows="3" placeholder="Nhập địa chỉ giao hàng"></textarea>
          </div>
          
          <div class="mt-4">
            <label class="form-label fw-bold small text-muted">GHI CHÚ (TÙY CHỌN)</label>
            <textarea v-model="note" class="form-control auth-input" rows="3" placeholder="Ví dụ: Lấy ít đường, sạc dự phòng..."></textarea>
          </div>
        </div>
      </div>

      <div class="payment-methods mb-4">
        <h5 class="fw-bold small text-muted mb-3 px-2">PHƯƠNG THỨC THANH TOÁN</h5>
        <div class="d-flex flex-column gap-3">
          <button class="btn btn-payment btn-transfer" :disabled="isSubmitting" @click="pay('transfer')">
            <div class="d-flex align-items-center">
              <div class="text-start">
                <div class="fw-bold">
                    <span v-if="isSubmitting" class="spinner-border spinner-border-sm me-2"></span>
                    Chuyển khoản Ngân hàng
                </div>
                <div class="small opacity-75">Tự động xác nhận qua VietQR</div>
              </div>
            </div>
            <span>→</span>
          </button>
          
          <button class="btn btn-payment btn-cash" :disabled="isSubmitting" @click="pay('cash')">
            <div class="d-flex align-items-center">
              <div class="text-start">
                <div class="fw-bold">
                    <span v-if="isSubmitting" class="spinner-border spinner-border-sm me-2"></span>
                    Thanh toán Tiền mặt
                </div>
                <div class="small opacity-75">Thanh toán trực tiếp tại quầy</div>
              </div>
            </div>
            <span>→</span>
          </button>
        </div>
      </div>

      <div class="support-section px-2">
        <button class="btn btn-support w-100" @click="callHotline">
          <span class="me-2">📞</span> Hỗ trợ Hotline: 0988992268
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');

.booking-summary-page {
  font-family: 'Inter', sans-serif;
  min-height: 100vh;
}

/* Header Styles */
.booking-header {
  z-index: 1010;
  background: rgba(255, 255, 255, 0.5);
  backdrop-filter: blur(10px);
  -webkit-backdrop-filter: blur(10px);
}

.header-back-btn {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  border: 1.5px solid #2D4F1E;
  background: white;
  color: #2D4F1E;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s ease;
  box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
}

.header-logo {
  height: 38px;
  width: auto;
}

/* Card Styles */
.section-card {
  border: none;
  border-radius: 24px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.03) !important;
}

.section-title {
  font-weight: 800;
  font-size: 1.2rem;
  letter-spacing: 0.05em;
  color: #1a1a1a;
}

/* Summary Items */
.summary-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.summary-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 10px;
}

.summary-item .label {
  color: #777;
  font-size: 0.95rem;
}

.summary-item .value {
  color: #333;
  text-align: right;
}

.extra-item {
  margin-bottom: 6px;
}

.extra-item .label {
  font-size: 0.85rem;
}

.total-item {
  border-top-style: dashed !important;
}

.total-item .label {
  color: #1a1a1a;
}

/* Inputs */
.auth-input {
  border: 1.5px solid #f0f0f0;
  border-radius: 12px;
  padding: 12px;
  background-color: #f9f9f9;
  transition: all 0.2s ease;
  width: 100%;
}

.auth-input:focus {
  border-color: #2D4F1E;
  background-color: #fff;
  box-shadow: none;
}

textarea.auth-input {
  height: 120px;
  resize: none;
}

/* Buttons */
.btn-payment {
  background: white;
  border: 1.5px solid #f0f0f0;
  border-radius: 20px;
  padding: 20px 24px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  position: relative;
  overflow: hidden;
}

.btn-payment:hover {
  border-color: #2D4F1E;
  transform: translateY(-4px) scale(1.01);
  box-shadow: 0 15px 30px rgba(45, 79, 30, 0.1);
  background-color: #fcfdfc;
}

.btn-payment:active {
  transform: translateY(-2px) scale(0.98);
}

.btn-payment::after {
  content: '';
  position: absolute;
  top: 0;
  left: -100%;
  width: 100%;
  height: 100%;
  background: linear-gradient(
    90deg,
    transparent,
    rgba(45, 79, 30, 0.03),
    transparent
  );
  transition: 0.5s;
}

.btn-payment:hover::after {
  left: 100%;
}

.btn-payment .fw-bold {
  font-size: 1.1rem;
  color: #1a1a1a;
  transition: color 0.3s ease;
}

.btn-payment:hover .fw-bold {
  color: #2D4F1E;
}

.btn-payment span {
  font-size: 1.2rem;
  color: #ccc;
  transition: all 0.3s ease;
}

.btn-payment:hover span {
  color: #2D4F1E;
  transform: translateX(5px);
}

.btn-support {
  background: #fdf6ec;
  color: #e67e22;
  border: none;
  border-radius: 16px;
  padding: 12px;
  font-weight: 600;
  font-size: 0.9rem;
}

.text-success {
  color: #2D4F1E !important;
}
</style>

