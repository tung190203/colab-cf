<script setup>
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useBooking } from '../composables/useBooking';
import { toast } from 'vue3-toastify'

const route = useRoute();
const router = useRouter();

const { selectedTable, endTime, total, formatVND, resetAll } = useBooking();

const isSuccess = ref(false);
const message = ref('');
const loading = ref(true);

onMounted(async () => {
  const resultCode = route.query.resultCode;
  const orderId = route.query.orderId || '';
  const msg = route.query.message || '';

  if (resultCode !== undefined) {
    if (resultCode === '0') {
      isSuccess.value = true;
      message.value = 'Thanh toán thành công!';
    } else {
      isSuccess.value = false;
      message.value = msg || 'Giao dịch thất bại hoặc bị hủy.';
      // Gọi callback ipn thủ công khi thất bại
      await fetch('/api/momo/callback', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ resultCode, orderId }),
      });
    }
  } else {
    isSuccess.value = true;
    message.value = 'Đặt chỗ thành công!';
  }
  loading.value = false;
});

function goHome() {
  resetAll();
  router.push('/');
}

function copyToClipboard(text) {
  navigator.clipboard.writeText(text).then(() => {
    toast.success('Đã sao chép mật khẩu WiFi!', {
      autoClose: 1500,
      position: 'bottom-center',
      hideProgressBar: true,
    });
  }).catch(err => {
    console.error('Lỗi khi copy: ', err);
  });
}
</script>

<template>
  <div class="status-page">
    <!-- Header -->
    <header class="booking-header sticky-top py-3 mb-4">
      <div class="container d-flex justify-content-center px-4">
        <div class="header-brand">
          <img src="../../images/logo.png" alt="logo" class="header-logo" />
        </div>
      </div>
    </header>

    <div class="container pb-5">
      <div class="card section-card mb-4">
        <div v-if="loading" class="card-body p-5 text-center">
          <div class="spinner-border text-success" role="status">
            <span class="visually-hidden">Đang xử lý...</span>
          </div>
          <p class="mt-3 text-muted fw-medium">Đang xác nhận giao dịch...</p>
        </div>

        <div v-else class="card-body p-4 p-md-5 text-center">
          <!-- Success Icon -->
          <div v-if="isSuccess" class="status-icon success mb-4">
            <div class="icon-circle">
              <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" fill="currentColor" class="bi bi-check-lg" viewBox="0 0 16 16">
                <path d="M12.736 3.97a.733.733 0 0 1 1.047 0c.286.289.29.756.01 1.05L7.88 12.01a.733.733 0 0 1-1.065.02L3.217 8.384a.757.757 0 0 1 0-1.06.733.733 0 0 1 1.047 0l3.052 3.093 5.42-6.447a.015.015 0 0 1 .015-.01z"/>
              </svg>
            </div>
          </div>

          <!-- Error Icon -->
          <div v-else class="status-icon error mb-4">
            <div class="icon-circle">
              <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" fill="currentColor" class="bi bi-exclamation-lg" viewBox="0 0 16 16">
                <path d="M7.005 3.1a1 1 0 1 1 1.99 0l-.388 6.35a.61.61 0 0 1-1.214 0L7.005 3.1ZM7 12a1 1 0 1 1 2 0 1 1 0 0 1-2 0Z"/>
              </svg>
            </div>
          </div>

          <h3 class="status-title mb-2" :class="{ 'text-success': isSuccess, 'text-danger': !isSuccess }">
            {{ message }}
          </h3>
          <p class="status-desc text-muted mb-4 px-md-5">
            {{ isSuccess ? 'Cảm ơn bạn đã tin tưởng và sử dụng dịch vụ tại Colab Coffee.' : 'Vui lòng kiểm tra lại giao dịch hoặc liên hệ bộ phận hỗ trợ.' }}
          </p>

          <div v-if="isSuccess" class="booking-receipt mb-4">
            <div class="receipt-header mb-3">
              <span class="receipt-label">CHI TIẾT ĐẶT CHỖ</span>
            </div>
            <div class="receipt-body">
              <div class="detail-item">
                <span class="label">Vị trí bàn</span>
                <span class="value fw-bold text-dark">{{ selectedTable || '-' }}</span>
              </div>
              <div class="detail-item">
                <span class="label">Thời gian kết thúc</span>
                <span class="value text-dark">{{ endTime }}</span>
              </div>
              <div class="receipt-divider my-3"></div>
              <div class="detail-item total">
                <span class="label">Tổng thanh toán</span>
                <span class="value text-success fw-bolder fs-5">{{ formatVND(total) }}</span>
              </div>
            </div>
          </div>

          <!-- WiFi Information -->
          <div v-if="isSuccess" class="wifi-section mb-5">
            <div class="wifi-card p-3">
              <div class="d-flex align-items-center justify-content-center mb-3">
                <div class="wifi-icon-bg me-2">
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-wifi" viewBox="0 0 16 16">
                    <path d="M15.384 6.115a.485.485 0 0 0-.047-.736A12.444 12.444 0 0 0 8 4 12.444 12.444 0 0 0 .663 5.379a.485.485 0 0 0-.048.736.518.518 0 0 0 .668.05A11.448 11.448 0 0 1 8 5c3.166 0 6.13 1.286 8.328 3.429.204.2.54.2.744 0a.508.508 0 0 0 .05-.668z"/>
                    <path d="M11.697 9.821a.485.485 0 0 0-.031-.713A7.478 7.478 0 0 0 8 8a7.478 7.478 0 0 0-3.666 1.108.485.485 0 0 0-.031.713.51.51 0 0 0 .641.05A6.478 6.478 0 0 1 8 9c1.78 0 3.442.712 4.666 1.971.196.203.53.203.726 0a.507.507 0 0 0 .03-.665z"/>
                    <path d="M8.5 11.729a.51.51 0 0 0 .656.03 3.484 3.484 0 0 1 3.228.873c.198.19.523.19.721 0a.507.507 0 0 0 .03-.664 4.484 4.484 0 0 0-3.957-1.321.485.485 0 0 0-.028.71l.65.65zm-1 0a.51.51 0 0 1-.656.03 3.484 3.484 0 0 0-3.228.873.51.51 0 0 1-.721 0 .507.507 0 0 1-.03-.664 4.484 4.484 0 0 1 3.957-1.321.485.485 0 0 1 .028.71l-.65.65z"/>
                    <path d="M8 13a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/>
                  </svg>
                </div>
                <span class="wifi-title">KẾT NỐI WIFI MIỄN PHÍ</span>
              </div>
              <div class="row g-2">
                <div class="col-6">
                  <div class="wifi-item">
                    <span class="wifi-label">Tên WiFi</span>
                    <span class="wifi-value">Colab_5G</span>
                  </div>
                </div>
                <div class="col-6">
                  <div class="wifi-item copy-active" @click="copyToClipboard('colab2024')" title="Nhấn để copy">
                    <span class="wifi-label">Mật khẩu</span>
                    <span class="wifi-value d-flex align-items-center">
                      colab2024
                      <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" class="bi bi-copy ms-1 opacity-50" viewBox="0 0 16 16">
                        <path fill-rule="evenodd" d="M4 2a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V2Zm2-1a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1H6Z"/>
                        <path d="M2 5a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1v-1h1v1a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h1v1H2Z"/>
                      </svg>
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="action-section">
            <button class="btn btn-home w-100 py-3" @click="goHome">
              {{ isSuccess ? 'HOÀN TẤT' : 'QUAY LẠI TRANG CHỦ' }}
            </button>
          </div>
        </div>
      </div>

      <div class="support-section px-2 text-center">
        <p class="small text-muted mb-3">Gặp sự cố với đơn hàng? Liên hệ ngay</p>
        <button class="btn btn-support w-100" @click="window.location.href = 'tel:0988992268'">
          <span class="me-2">📞</span> Hotline: 0988992268
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');

.status-page {
  font-family: 'Inter', sans-serif;
}

/* Header Styles */
.booking-header {
  z-index: 1010;
  background: rgba(255, 255, 255, 0.8);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
}

.header-logo {
  height: 80px;
  width: auto;
}

/* Card Styles */
.section-card {
  border: none;
  border-radius: 28px;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.04) !important;
  overflow: hidden;
  margin-top: 1rem;
  background: #ffffff;
}

/* Icons */
.status-icon {
  display: flex;
  justify-content: center;
}

.icon-circle {
  width: 90px;
  height: 90px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
  transition: transform 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}

.icon-circle:hover {
  transform: scale(1.05);
}

.success .icon-circle {
  background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
  color: #2D4F1E;
}

.error .icon-circle {
  background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%);
  color: #c62828;
}

.icon-circle::after {
  content: '';
  position: absolute;
  top: -8px;
  left: -8px;
  right: -8px;
  bottom: -8px;
  border-radius: 50%;
  border: 2px solid currentColor;
  opacity: 0.1;
  animation: pulse 2.5s infinite;
}

@keyframes pulse {
  0% { transform: scale(1); opacity: 0.2; }
  100% { transform: scale(1.4); opacity: 0; }
}

.status-title {
  font-weight: 800;
  font-size: 1.75rem;
  letter-spacing: -0.03em;
  color: #1a1a1a;
}

.status-desc {
  font-size: 1rem;
  line-height: 1.6;
  color: #666;
}

/* Booking Receipt Style */
.booking-receipt {
  background-color: #fcfcfc;
  border: 1px solid #f0f0f0;
  border-radius: 24px;
  padding: 24px;
  position: relative;
  box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);
}

.receipt-header {
  border-bottom: 1px dashed #eee;
  padding-bottom: 12px;
}

.receipt-label {
  font-size: 0.75rem;
  font-weight: 700;
  color: #999;
  letter-spacing: 0.1em;
}

.receipt-body {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.detail-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.detail-item .label {
  color: #777;
  font-size: 0.95rem;
}

.detail-item .value {
  font-size: 1rem;
}

.receipt-divider {
  border-top: 2px dotted #eee;
  height: 1px;
  width: 100%;
}

.detail-item.total .label {
  color: #1a1a1a;
  font-weight: 700;
}

/* WiFi Section Styles */
.wifi-section {
  position: relative;
}

.wifi-card {
  background: linear-gradient(135deg, #fdfdfd 0%, #f5f7f2 100%);
  border: 1px solid #e2e8df;
  border-radius: 20px;
  box-shadow: 0 4px 15px rgba(45, 79, 30, 0.05);
}

.wifi-icon-bg {
  background: #2D4F1E;
  color: white;
  width: 28px;
  height: 28px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
}

.wifi-title {
  font-size: 0.75rem;
  font-weight: 800;
  color: #2D4F1E;
  letter-spacing: 0.1em;
}

.wifi-item {
  background: white;
  padding: 10px;
  border-radius: 12px;
  display: flex;
  flex-direction: column;
  align-items: center;
  border: 1px solid #f0f0f0;
  transition: all 0.2s ease;
}

.wifi-item.copy-active {
  cursor: pointer;
}

.wifi-item.copy-active:hover {
  background-color: #f0f7ef;
  border-color: #2D4F1E;
}

.wifi-item.copy-active:active {
  transform: scale(0.95);
}

.wifi-label {
  font-size: 0.7rem;
  color: #999;
  text-transform: uppercase;
  margin-bottom: 2px;
}

.wifi-value {
  font-size: 0.85rem;
  font-weight: 700;
  color: #1a1a1a;
}

/* Buttons */
.btn-home {
  background: linear-gradient(135deg, #2D4F1E 0%, #1e3614 100%);
  color: white;
  border: none;
  border-radius: 20px;
  font-weight: 700;
  font-size: 1.1rem;
  letter-spacing: 0.02em;
  transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
  box-shadow: 0 12px 24px rgba(45, 79, 30, 0.25);
}

.btn-home:hover {
  background: linear-gradient(135deg, #1e3614 0%, #14250d 100%);
  transform: translateY(-3px);
  box-shadow: 0 18px 30px rgba(45, 79, 30, 0.35);
  color: white;
}

.btn-home:active {
  transform: translateY(-1px);
}

.btn-support {
  background: #ffffff;
  color: #2D4F1E;
  border: 1px solid #e0e0e0;
  border-radius: 18px;
  padding: 14px;
  font-weight: 600;
  font-size: 0.95rem;
  transition: all 0.3s ease;
}

.btn-support:hover {
  background: #f8f9fa;
  border-color: #2D4F1E;
  color: #2D4F1E;
}

.text-success {
  color: #2D4F1E !important;
}

/* Responsive */
@media (max-width: 576px) {
  .section-card {
    border-radius: 24px;
    margin-top: 0.5rem;
  }
  
  .status-title {
    font-size: 1.5rem;
  }
  
  .booking-receipt {
    padding: 20px;
  }
}

</style>
